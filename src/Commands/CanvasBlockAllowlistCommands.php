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
    $allowlistPath = dirname(DRUPAL_ROOT) . '/config/canvas-block-allowlist.yml';
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

}
