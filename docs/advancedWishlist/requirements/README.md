# Development Guidelines for Advanced Wishlist System

This directory contains comprehensive guidelines and requirements for developers working on the Advanced Wishlist System plugin for Shopware 6. These guidelines ensure consistent, high-quality code that follows best practices and modern development standards.

## Guidelines Overview

The following guidelines are available:

1. [Git Guidelines](./git-guidelines.md) - Best practices for version control, commit messages, branching strategy, and pull requests
2. [Development Guidelines](./development-guidelines.md) - Coding standards, architecture principles, and best practices for plugin development
3. [Error Handling Guidelines](./error-guidelines.md) - Standards for error handling, logging, and debugging
4. [PHP 8.4 Requirements](./php84-requirements.md) - Specific requirements and features for PHP 8.4 compatibility

## Core Principles

All development work on the Advanced Wishlist System should adhere to these core principles:

1. **Quality First** - Code quality is non-negotiable; prioritize clean, maintainable code over quick fixes
2. **Test-Driven Development** - Write tests before implementing features
3. **Documentation** - Document all code, APIs, and architectural decisions
4. **Security** - Follow security best practices at all times
5. **Performance** - Optimize for performance without sacrificing maintainability
6. **Accessibility** - Ensure the plugin is accessible to all users
7. **Compatibility** - Maintain compatibility with Shopware 6 core and other common plugins

## Development Workflow

1. Review the relevant feature specification in the `/docs/advancedWishlist` directory
2. Create a feature branch following the Git guidelines
3. Implement the feature following the development guidelines
4. Write comprehensive tests (unit, integration, and end-to-end)
5. Document the feature in code and update relevant documentation
6. Submit a pull request for review
7. Address review feedback
8. Merge to develop branch after approval

## Quality Assurance

All code must pass the following quality checks before being merged:

1. All tests pass (unit, integration, end-to-end)
2. Code meets PSR-12 coding standards
3. Static analysis tools show no errors (PHPStan level 8)
4. Security analysis tools show no vulnerabilities
5. Code review by at least one other developer
6. Documentation is complete and up-to-date

## Getting Started

New developers should:

1. Read all guidelines in this directory
2. Set up the development environment according to the [Development Guidelines](./development-guidelines.md)
3. Familiarize themselves with the [Technical Architecture](../wishlist-architecture.md)
4. Review the [Product Requirements Document](../prd-advanced-wishlist.md)
5. Start with a small task to get familiar with the codebase

## Questions and Support

For questions about these guidelines or development support, contact:

- Technical Lead: tech@wishlist.dev
- Development Team: dev@wishlist.dev