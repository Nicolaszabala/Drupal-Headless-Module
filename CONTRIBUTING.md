# Contributing to Drupal Headless Module

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Create a new branch for your feature or bugfix
4. Make your changes
5. Run tests and code quality checks
6. Submit a pull request

## Development Setup

### Prerequisites

- Drupal 10.3+ or Drupal 11
- PHP 8.1+
- Composer
- Git

### Local Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/Drupal-Headless-Module.git
cd Drupal-Headless-Module

# Install dependencies (if developing standalone)
composer install
```

### Running Tests

Before submitting a PR, ensure all tests pass:

```bash
# Unit tests
vendor/bin/phpunit drupal_headless/tests/src/Unit

# Kernel tests
vendor/bin/phpunit drupal_headless/tests/src/Kernel

# Functional tests
vendor/bin/phpunit drupal_headless/tests/src/Functional

# All tests
vendor/bin/phpunit drupal_headless/tests
```

### Code Quality

We follow Drupal coding standards. Run these checks before committing:

```bash
# Check coding standards
vendor/bin/phpcs --standard=Drupal,DrupalPractice drupal_headless

# Fix automatic issues
vendor/bin/phpcbf --standard=Drupal,DrupalPractice drupal_headless

# Static analysis
vendor/bin/phpstan analyse drupal_headless
```

## Coding Standards

- Follow [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards)
- Write meaningful commit messages
- Add PHPDoc comments to all functions
- Keep functions focused and small
- Write tests for new features

## Commit Messages

Use clear, descriptive commit messages:

```
Add consumer creation wizard

- Implement multi-step form for consumer setup
- Add validation for OAuth2 credentials
- Include tests for wizard functionality
```

### Commit Message Format

- First line: Brief summary (50 chars max)
- Blank line
- Detailed description if needed
- List specific changes with bullet points

## Pull Request Process

1. Update documentation if you change functionality
2. Add tests for new features
3. Ensure all tests pass
4. Update CHANGELOG.md with your changes
5. Request review from maintainers

### PR Title Format

```
[Issue #123] Add feature X
[Bugfix] Fix Y error
[Docs] Update README
```

## Testing Guidelines

### Writing Tests

- **Unit Tests**: Test individual methods in isolation
- **Kernel Tests**: Test services with minimal Drupal bootstrap
- **Functional Tests**: Test full user workflows

### Test Structure

```php
/**
 * Tests for MyService.
 *
 * @group drupal_headless
 * @coversDefaultClass \Drupal\drupal_headless\Service\MyService
 */
class MyServiceTest extends UnitTestCase {

  /**
   * Tests myMethod does X.
   *
   * @covers ::myMethod
   */
  public function testMyMethod() {
    // Arrange
    $service = new MyService();

    // Act
    $result = $service->myMethod();

    // Assert
    $this->assertEquals('expected', $result);
  }
}
```

## Documentation

- Update README.md for user-facing changes
- Add inline comments for complex logic
- Update CHANGELOG.md following [Keep a Changelog](https://keepachangelog.com/)
- Add PHPDoc blocks to all classes and methods

## Security

- Never commit credentials or secrets
- Report security issues privately to maintainers
- Follow [Drupal Security best practices](https://www.drupal.org/docs/security-in-drupal)

## Code Review

All submissions require review. Reviewers will check:

- Code quality and standards compliance
- Test coverage
- Documentation completeness
- Security implications
- Performance impact

## Questions?

- Open an issue for bugs or feature requests
- Join discussions in existing issues
- Reach out to maintainers for guidance

## License

By contributing, you agree that your contributions will be licensed under GPL-2.0-or-later.
