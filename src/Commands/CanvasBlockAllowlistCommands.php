<?php

declare(strict_types=1);

namespace Drupal\canvas_block_allowlist\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\canvas\Entity\Component;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * Drush commands for Canvas block allowlist reporting.
 */
final class CanvasBlockAllowlistCommands extends DrushCommands {

  private const DEFAULT_ALLOWLIST_PATH = '/assets/config/canvas/canvas-block-allowlist.yml';

  private const LEGACY_ALLOWLIST_PATH = '/config/canvas/canvas-block-allowlist.yml';

  private const OLDER_ALLOWLIST_PATH = '/config/canvas-block-allowlist.yml';

  /**
   * Constructs a new command handler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Lists Canvas block allowlist config, allowed blocks, and allowable blocks.
   */
  #[CLI\Command(name: 'canvas-block-allowlist:list', aliases: ['cbal-list'])]
  #[CLI\Usage(name: 'drush canvas-block-allowlist:list', description: 'Show configured allowlist, currently allowed blocks, and all block components that can be allowed.')]
  public function list(): int {
    $allowlistPath = $this->resolveAllowlistPath();
    $configured = $this->readConfiguredAllowlist($allowlistPath);

    $storage = $this->entityTypeManager->getStorage('component');
    $blockComponents = $storage->loadByProperties(['source' => 'block']);
    $rows = [];
    foreach ($blockComponents as $component) {
      if (!$component instanceof Component) {
        continue;
      }
      $rows[] = [
        'id' => $component->id(),
        'label' => (string) $component->label(),
        'enabled' => $component->status() ? 'yes' : 'no',
        'in_allowlist_file' => in_array($component->id(), $configured, TRUE) ? 'yes' : 'no',
      ];
    }

    usort($rows, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));

    $allowedNow = array_values(array_filter($rows, static fn(array $row): bool => $row['enabled'] === 'yes'));

    $this->io()->section('Configured allowlist file');
    $this->output()->writeln('Path: ' . $allowlistPath);
    if ($configured === []) {
      $this->output()->writeln('- none');
    }
    else {
      foreach ($configured as $id) {
        $this->output()->writeln('- ' . $id);
      }
    }

    $this->io()->newLine();
    $this->io()->section('Currently allowed in Canvas');
    if ($allowedNow === []) {
      $this->output()->writeln('No block components are currently enabled.');
    }
    else {
      $this->io()->table(['ID', 'Label'], array_map(static fn(array $row): array => [$row['id'], $row['label']], $allowedNow));
    }

    $this->io()->newLine();
    $this->io()->section('Block components that can be allowed');
    if ($rows === []) {
      $this->output()->writeln('No Canvas block components are available.');
    }
    else {
      $this->io()->table(
        ['ID', 'Label', 'Enabled now', 'In allowlist file'],
        array_map(static fn(array $row): array => [$row['id'], $row['label'], $row['enabled'], $row['in_allowlist_file']], $rows),
      );
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Enables a block component in Canvas and records it in the allowlist file.
    *
    * @param string $blockId
    *   Canvas block component ID, for example block.system_branding_block or
    *   system_branding_block.
   */
  #[CLI\Command(name: 'canvas-block-allowlist:add', aliases: ['cbal-add'])]
  #[CLI\Usage(name: 'drush canvas-block-allowlist:add block.system_branding_block', description: 'Enable a block-backed Canvas component and add it to the allowlist YAML file.')]
  #[CLI\Usage(name: 'drush canvas-block-allowlist:add system_branding_block', description: 'Enable a block-backed Canvas component by block plugin ID and add it to the allowlist YAML file.')]
  public function add(string $blockId): int {
    $component = $this->loadBlockComponent($blockId);
    if (!$component instanceof Component) {
      $normalized = $this->normalizeBlockComponentId($blockId);
      $this->io()->error(sprintf('Canvas block component "%s" was not found.', $normalized));
      return self::EXIT_FAILURE;
    }

    $allowlistPath = $this->resolveAllowlistPath();
    $configured = $this->readConfiguredAllowlist($allowlistPath);
    $componentId = $component->id();

    if (!$component->status()) {
      $component->enable()->save();
    }

    if (!in_array($componentId, $configured, TRUE)) {
      $configured[] = $componentId;
      sort($configured);
      $this->writeConfiguredAllowlist($allowlistPath, $configured);
    }

    $this->io()->success(sprintf('Canvas block component "%s" is enabled and present in %s.', $componentId, $allowlistPath));
    return self::EXIT_SUCCESS;
  }

  /**
   * Reads configured component IDs from the allowlist YAML file.
   *
   * @return list<string>
   *   A de-duplicated list of component IDs.
   */
  private function readConfiguredAllowlist(string $path): array {
    if (!is_file($path)) {
      return [];
    }

    try {
      $parsed = Yaml::parseFile($path);
    }
    catch (\Throwable) {
      return [];
    }

    if (!is_array($parsed)) {
      return [];
    }

    $blocks = $parsed['blocks'] ?? [];
    if (!is_array($blocks)) {
      return [];
    }

    $normalized = [];
    foreach ($blocks as $candidate) {
      if (!is_string($candidate)) {
        continue;
      }
      $id = trim($candidate);
      if ($id === '') {
        continue;
      }
      $normalized[$id] = TRUE;
    }

    return array_keys($normalized);
  }

  /**
   * Writes configured component IDs to the allowlist YAML file.
   *
   * @param list<string> $configured
   *   The component IDs to store.
   */
  private function writeConfiguredAllowlist(string $path, array $configured): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, TRUE) && !is_dir($directory)) {
      throw new \RuntimeException(sprintf('Unable to create allowlist directory "%s".', $directory));
    }

    $yaml = Yaml::dump(['blocks' => array_values($configured)], 4, 2);
    if (file_put_contents($path, $yaml) === FALSE) {
      throw new \RuntimeException(sprintf('Unable to write allowlist file "%s".', $path));
    }
  }

  /**
   * Resolves the preferred allowlist file path.
   */
  private function resolveAllowlistPath(): string {
    $projectRoot = dirname(DRUPAL_ROOT);
    $preferredPath = $projectRoot . self::DEFAULT_ALLOWLIST_PATH;
    $legacyPath = $projectRoot . self::LEGACY_ALLOWLIST_PATH;
    $olderPath = $projectRoot . self::OLDER_ALLOWLIST_PATH;

    if (is_file($preferredPath)) {
      return $preferredPath;
    }
    if (is_file($legacyPath)) {
      return $legacyPath;
    }
    if (is_file($olderPath)) {
      return $olderPath;
    }

    return $preferredPath;
  }

  /**
   * Loads a block-backed Canvas component by full component ID or block ID.
   */
  private function loadBlockComponent(string $blockId): ?Component {
    $normalizedId = $this->normalizeBlockComponentId($blockId);
    $storage = $this->entityTypeManager->getStorage('component');
    $component = $storage->load($normalizedId);
    if ($component instanceof Component && $component->get('source') === 'block') {
      return $component;
    }

    $matches = $storage->loadByProperties([
      'source' => 'block',
      'source_local_id' => $this->stripBlockComponentPrefix($blockId),
    ]);
    foreach ($matches as $match) {
      if ($match instanceof Component) {
        return $match;
      }
    }

    return NULL;
  }

  /**
   * Normalizes an input value to a Canvas block component ID.
   */
  private function normalizeBlockComponentId(string $blockId): string {
    $normalized = trim($blockId);
    if (str_starts_with($normalized, 'block.')) {
      return $normalized;
    }

    return 'block.' . $normalized;
  }

  /**
   * Strips the Canvas block component prefix from a component ID.
   */
  private function stripBlockComponentPrefix(string $blockId): string {
    $normalized = trim($blockId);
    if (str_starts_with($normalized, 'block.')) {
      return substr($normalized, strlen('block.'));
    }

    return $normalized;
  }

}
