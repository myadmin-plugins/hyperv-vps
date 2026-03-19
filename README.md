# MyAdmin Hyper-V VPS Plugin

[![Tests](https://github.com/detain/myadmin-hyperv-vps/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-hyperv-vps/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-hyperv-vps/version)](https://packagist.org/packages/detain/myadmin-hyperv-vps)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-hyperv-vps/downloads)](https://packagist.org/packages/detain/myadmin-hyperv-vps)
[![License](https://poser.pugx.org/detain/myadmin-hyperv-vps/license)](https://packagist.org/packages/detain/myadmin-hyperv-vps)

A MyAdmin plugin for managing Microsoft Hyper-V virtual private servers. This package provides full lifecycle management of Hyper-V VPS instances including provisioning, configuration, power control, disk resizing, IOPS management, and teardown through a SOAP-based API integration.

## Features

- Virtual machine creation with configurable RAM, disk, and OS template
- Power management (start, stop, reboot, pause, resume)
- Dynamic CPU and memory resizing via slice-based allocation
- IOPS rate limiting with configurable min/max thresholds
- Automated IP assignment and network configuration
- Administrator password management
- Event-driven architecture using Symfony EventDispatcher
- Queue-based operation sequencing for multi-step workflows

## Installation

```sh
composer require detain/myadmin-hyperv-vps
```

## Requirements

- PHP >= 5.0
- ext-soap
- Symfony EventDispatcher ^5.0

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

## License

This package is licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.en.html) license.
