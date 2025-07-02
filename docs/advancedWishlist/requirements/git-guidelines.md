# Git Guidelines for Advanced Wishlist System

This document outlines the Git workflow and best practices for the Advanced Wishlist System plugin development. Following these guidelines ensures a clean, traceable history and efficient collaboration.

## Branching Strategy

We follow a modified GitFlow workflow:

```
main
  └── develop
      ├── feature/wishlist-crud
      ├── feature/social-sharing
      ├── bugfix/item-deletion-error
      └── release/1.0.0
```

### Branch Types

- **main**: Production-ready code only
- **develop**: Integration branch for features
- **feature/[name]**: New features or enhancements
- **bugfix/[issue-number]**: Bug fixes
- **release/[version]**: Release preparation
- **hotfix/[issue-number]**: Urgent production fixes

### Branch Naming Conventions

- Use lowercase with hyphens
- Feature branches: `feature/short-feature-description`
- Bugfix branches: `bugfix/issue-number-short-description`
- Release branches: `release/x.y.z`
- Hotfix branches: `hotfix/issue-number-short-description`

Examples:
- `feature/multiple-wishlists`
- `bugfix/123-item-deletion-error`
- `release/1.2.0`
- `hotfix/456-security-vulnerability`

## Commit Messages

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- **feat**: A new feature
- **fix**: A bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, missing semicolons, etc.)
- **refactor**: Code changes that neither fix bugs nor add features
- **perf**: Performance improvements
- **test**: Adding or correcting tests
- **chore**: Changes to the build process, tools, etc.

### Scope

The scope should be the name of the module affected (wishlist, sharing, analytics, etc.)

### Subject

- Use imperative, present tense: "change" not "changed" nor "changes"
- Don't capitalize the first letter
- No period (.) at the end

### Examples

```
feat(wishlist): add multiple wishlist support

Implement the ability for users to create and manage multiple wishlists.
Each wishlist has its own name, privacy settings, and can be shared independently.

Closes #123
```

```
fix(api): prevent duplicate items in wishlist

The API was not checking for existing items before adding new ones,
resulting in duplicate entries. This adds a uniqueness check.

Fixes #456
```

## Pull Requests

### PR Title

Follow the same convention as commit messages:

```
<type>(<scope>): <subject>
```

### PR Description Template

```
## Description
[Detailed description of changes]

## Related Issues
Closes #[issue-number]

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## How Has This Been Tested?
- [ ] Unit tests
- [ ] Integration tests
- [ ] Manual tests

## Checklist
- [ ] My code follows the project's coding standards
- [ ] I have added tests that prove my fix/feature works
- [ ] All tests pass locally
- [ ] I have updated the documentation accordingly
- [ ] I have added appropriate comments to my code
```

### PR Review Process

1. At least one approval is required before merging
2. All automated checks must pass:
   - CI build
   - Code style checks
   - Unit and integration tests
   - Static analysis
3. No merge conflicts with the target branch
4. All discussions must be resolved

## Tagging and Releases

### Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR**: Incompatible API changes
- **MINOR**: Backwards-compatible new functionality
- **PATCH**: Backwards-compatible bug fixes

### Release Process

1. Create a release branch from develop: `release/x.y.z`
2. Make final adjustments and version bumps
3. Merge to main with a tag: `x.y.z`
4. Merge back to develop

### Hotfix Process

1. Create a hotfix branch from main: `hotfix/issue-number-description`
2. Fix the issue
3. Merge to main with a tag: `x.y.z+1`
4. Merge back to develop

## Git Best Practices

1. **Commit Often**: Make small, focused commits
2. **Pull Before Push**: Always pull the latest changes before pushing
3. **Rebase Feature Branches**: Keep feature branches up to date with develop
4. **Squash Commits**: Squash trivial commits before merging to develop
5. **Never Force Push** to shared branches (develop, main)
6. **Delete Branches** after merging
7. **Use .gitignore** for environment-specific files

## Git Hooks

We use Git hooks to enforce quality standards:

1. **pre-commit**: Runs code style checks and static analysis
2. **commit-msg**: Validates commit message format
3. **pre-push**: Runs tests before pushing

## Git Configuration

Recommended Git configuration:

```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
git config --global core.autocrlf input
git config --global pull.rebase true
```

## Troubleshooting

For Git-related issues, contact the technical lead or refer to the [Git documentation](https://git-scm.com/doc).