# Overwatch Module for Drupal

## Description

The `Overwatch` module is designed to transmit events to Overwatch platform. It captures various entity events like insert, update, and delete.

## Requirements

- Drupal ^9 || ^10
- `hook_event_dispatcher` module https://www.drupal.org/project/hook_event_dispatcher
- `core_event_dispatcher` module https://packagist.org/packages/drupal/core-event-dispatcher

## Installation

### Step 1: Download Dependencies

Before enabling the `Overwatch` module, ensure you have downloaded the following dependencies:

1. `hook_event_dispatcher` module
2. `core_event_dispatcher` module
3. `queue_ui` module

You can download these modules using Composer:

```bash
composer require drupal/hook_event_dispatcher
composer require drupal/queue_ui

### Step 2: Enable dependencies

```bash
drush en hook_event_dispatcher core_event_dispatcher queue_ui -y
drush en overwatch -y
