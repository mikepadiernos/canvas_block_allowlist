# Canvas Block Allowlist

A small Drupal custom module that helps you review and manage which Canvas block components are enabled and which ones are recorded in the allowlist config file.

## Purpose

This module adds a Drush command to compare:

- the configured allowlist file at `config/canvas-block-allowlist.yml`
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

- `config/canvas-block-allowlist.yml`

Example:

```yaml
blocks:
  - card
  - hero
  - content
```

The command will normalize the list and compare the configured IDs against the available block components in Canvas.

## Usage

Run:

```bash
drush canvas-block-allowlist:list
```

Alias:

```bash
drush cbal-list
```

The command prints three sections:

1. Configured allowlist file
2. Currently allowed in Canvas
3. Block components that can be allowed

## What the command shows

For each Canvas block component, the command displays:

- component ID
- label
- whether it is enabled now
- whether it is present in the allowlist file

## Notes

- The file is read from the project config directory adjacent to the Drupal root (`../config/canvas-block-allowlist.yml` from the Drupal root).
- If the file is missing or invalid YAML, the command treats it as empty and continues safely.

## Development

This module is intentionally lightweight and focused on auditing Canvas block allowlist state.
