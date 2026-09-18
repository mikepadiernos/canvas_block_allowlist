# Canvas Block Allowlist

A small Drupal custom module that helps you review and manage which Canvas block components are enabled and which ones are recorded in the allowlist config file.

## Purpose

This module adds Drush commands to compare and update:

- the configured allowlist file at `assets/config/canvas/canvas-block-allowlist.yml`
- the block components currently enabled in Canvas
- all block-based Canvas components available for allowlisting

This is useful when you want to quickly audit whether your allowlist matches the current Canvas configuration.

## Requirements

- Drupal 11
- Canvas module enabled
- Drush

## Installation

1. Copy this module into your Drupal custom modules directory, for example:
   - `web/modules/custom/canvas_block_allowlist`
2. Enable the module:
   - `drush en canvas_block_allowlist`

## Allowlist file

The module reads its configured allowlist from:

- `assets/config/canvas/canvas-block-allowlist.yml`

If the legacy project-root config files still exist, the command will fall back to those locations for compatibility.

Example:

```yaml
blocks:
  - card
  - hero
  - content
```

The command will normalize the list and compare the configured IDs against the available block components in Canvas.

## Usage

List current state:

```bash
drush canvas-block-allowlist:list
```

Example in a DDEV-based local environment:

```bash
ddev drush canvas-block-allowlist:list
```

Add a block component to Canvas and the allowlist, for example the site branding block:

```bash
ddev drush canvas-block-allowlist:add system_branding_block
```

Alias:

```bash
drush cbal-list
```

Add alias:

```bash
drush cbal-add system_branding_block
```

The list command prints three sections:

1. Configured allowlist file
2. Currently allowed in Canvas
3. Block components that can be allowed

## What the command shows

For each Canvas block component, the command displays:

- component ID
- label
- whether it is enabled now
- whether it is present in the allowlist file

The add command:

- enables the matching block-backed Canvas component
- creates the allowlist file in `assets/config/canvas/` if it does not exist yet
- adds the component ID to the `blocks` list if it is not already present

## Notes

- The file is read from the project asset config directory adjacent to the Drupal root (`../assets/config/canvas/canvas-block-allowlist.yml` from the Drupal root, with legacy fallbacks to `../config/canvas/canvas-block-allowlist.yml` and `../config/canvas-block-allowlist.yml`).
- If the file is missing or invalid YAML, the command treats it as empty and continues safely.

## Development

This module is intentionally lightweight and focused on auditing Canvas block allowlist state.
