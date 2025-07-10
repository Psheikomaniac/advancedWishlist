# Technology Stack Compatibility Updates

## Overview
This document outlines the necessary updates to ensure the AdvancedWishlist plugin is compatible with the latest technology stack requirements for Shopware 6.6 and beyond.

## Current Status
Based on the code review findings (July 2025), the plugin has several compatibility issues:

| Component | Plugin Uses | Shopware 6.6 Requires | Latest Available | Status |
|-----------|-------------|----------------------|------------------|---------|
| PHP | 8.1+ | 8.2+ | 8.4 | ⚠️ Needs Update |
| Symfony | 5.x/6.x patterns | 7.x | 7.3.1 | ❌ Incompatible |
| Doctrine | 2.x patterns | 3.x | 3.x | ⚠️ Review Needed |
| Twig | 3.x | 3.x | 3.x | ✅ Compatible |

## Required Updates

### 1. PHP Version Requirement
- Update minimum PHP requirement to 8.3
- Ensure compatibility with PHP 8.4 features
- Update type declarations throughout the codebase

### 2. Symfony Compatibility
- Replace all annotation-based routing with PHP attributes
- Update controller annotations to attributes
- Implement attribute-based validation
- Update service definitions

### 3. Doctrine Compatibility
- Update Doctrine annotations to attributes
- Fix any deprecated repository methods
- Ensure compatibility with Doctrine 3.x

### 4. Dependencies Update
- Update composer.json with appropriate version constraints:
  ```json
  {
      "require": {
          "php": ">=8.3",
          "shopware/core": "~6.7.0",
          "symfony/framework-bundle": "~7.2",
          "symfony/validator": "~7.2",
          "doctrine/orm": "^3.0"
      }
  }
  ```

## Implementation Plan
1. Update composer.json dependencies
2. Convert all Symfony annotations to attributes
3. Update PHP type declarations
4. Fix Doctrine compatibility issues
5. Test with PHP 8.3 and 8.4

## Benefits
- Ensures long-term compatibility with Shopware 6.6+
- Leverages modern PHP features for better code quality
- Prepares the codebase for future updates
- Improves developer experience with modern patterns

## Next Steps
After completing these updates, we should proceed with security fixes and core functionality implementation as outlined in Phase 1 of the roadmap.