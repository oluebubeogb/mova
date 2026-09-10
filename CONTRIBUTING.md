# Contributing to Mova CMS

Thank you for considering contributing to Mova!

Mova aims to stay simple, fast, and pleasant to work with. We welcome bug fixes, improvements, new themes/plugins, and documentation.

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/YOUR-USERNAME/mova.git
   cd mova
   ```

2. Run with Docker (recommended):
   ```bash
   docker build -t mova .
   docker run -d -p 8080:80 \
     -v $(pwd)/storage:/var/www/storage \
     -v $(pwd)/public/mova-uploads:/var/www/public/mova-uploads \
     mova
   ```

3. Open http://localhost:8080/hq/install and create an owner account.

Requirements: PHP 8.1+, SQLite (pdo_sqlite), GD extension.

## Branching

- `main` — stable, production-ready code
- Feature branches: `feature/short-description`
- Bug fixes: `fix/short-description`

Always create a new branch from the latest `main`.

## Coding Guidelines

- Target PHP 8.1+
- Prefer simple, readable code over clever abstractions
- Follow the existing style and patterns in the codebase
- Keep functions and classes focused
- Add comments only when the intent is not obvious
- Do not introduce heavy external frameworks

## What We Welcome

- Bug fixes
- Performance improvements
- Security improvements
- New official themes or plugins
- Documentation improvements
- Better multi-site isolation
- Tests

## What to Avoid

- Large refactors without first opening an issue
- Breaking changes without discussion
- Committing `storage/`, uploaded files, SQLite databases, or `.env` files
- Adding unnecessary dependencies

## Pull Request Process

1. Open an issue first for anything non-trivial.
2. Fork the repository and create your branch.
3. Make your changes and test them locally (clean install + update path).
4. Keep the PR focused — one logical change per PR when possible.
5. Write a clear description of what the PR does and why.
6. Submit the PR against the `main` branch.

We will review PRs as soon as possible. Small, well-tested PRs are merged faster.

## Reporting Bugs

Please include:

- Mova version (`public/mova.json`)
- PHP version
- How you are running it (Coolify, Docker, shared hosting, etc.)
- Steps to reproduce
- Expected vs actual behaviour

## Code of Conduct

Be respectful and constructive. Harassment or toxic behaviour will not be tolerated.

Thank you for helping make Mova better!
