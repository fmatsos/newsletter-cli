# Akawaka Newsletter CLI

Génère automatiquement une newsletter de veille tech quotidienne à partir de flux RSS, avec résumés en français via Claude API.

## Architecture

```
src/
├── Domain/              # Logique métier pure (aucune dépendance externe)
│   ├── Model/           # Article, Category, DateWindow
│   ├── Port/            # Interfaces (FeedFetcher, FeedParser, Summarizer, Publisher, Renderer)
│   └── Service/         # DateWindowCalculator, ArticleCollector
├── Application/         # Cas d'utilisation
│   ├── DTO/             # NewsletterConfiguration, NewsletterResult
│   └── BuildNewsletterHandler.php
└── Infrastructure/      # Implémentations concrètes
    ├── Feed/            # HttpFeedFetcher, XmlFeedParser
    ├── Renderer/        # TwigNewsletterRenderer
    ├── Summarizer/      # ClaudeArticleSummarizer (#[SensitiveParameter])
    ├── Publisher/       # GithubDiscussionPublisher, FileNewsletterArchiver, CompositeNewsletterPublisher
    ├── Configuration/   # YamlConfigurationLoader
    └── Console/         # BuildNewsletterCommand (invokable + #[Option]), NullPublisher
```

## Attributs PHP utilisés

| Attribut | Usage |
|---|---|
| `#[AsCommand]` | Déclaration de la commande Symfony |
| `#[Option]` | Options CLI sur la méthode `__invoke()` (pattern invokable) |
| `#[Override]` | Toutes les implémentations d'interfaces (PHP 8.3) |
| `#[SensitiveParameter]` | Clé API dans `ClaudeArticleSummarizer` (PHP 8.2) |
| `#[CoversClass]` | Tests PHPUnit |
| `#[Test]` | Tests PHPUnit |

## Configuration

Copier `config/newsletter.yaml.dist` vers `config/newsletter.yaml` et adapter :

```bash
cp config/newsletter.yaml.dist config/newsletter.yaml
```

Le fichier `.dist` contient la configuration de référence (catégories, flux RSS, destinataires).

## Secrets GitHub requis

| Secret | Description |
|---|---|
| `ANTHROPIC_API_KEY` | Clé API Anthropic pour les résumés via Claude |

## Prérequis GitHub

- **Discussions** activées sur le repository
- Une catégorie de discussion (par défaut : `General`)

## Build PHAR

Le projet utilise [humbug/box](https://github.com/box-project/box) pour générer un PHAR autonome :

```bash
# Installation de box (si pas déjà disponible)
composer global require humbug/box

# Build
composer build:phar

# Test
php dist/newsletter.phar --version
```

Le PHAR contient uniquement le code PHP (`src/` + `vendor/`). Les fichiers `config/` et `templates/` restent **externes** pour permettre leur personnalisation sans rebuild.

## Release

Créer un tag pour déclencher le build automatique du PHAR :

```bash
git tag v1.0.0
git push --tags
```

Le workflow `release-phar.yml` génère le PHAR et l'attache à la release GitHub.

## Exécution locale

```bash
composer install
php bin/console newsletter:build --dry-run --output=newsletter.html -v
```

Ou avec le PHAR :

```bash
php dist/newsletter.phar newsletter:build --dry-run --output=newsletter.html -v
```

## Tests

```bash
composer test
```

## Workflow quotidien

Le workflow `newsletter.yml` s'exécute automatiquement du lundi au vendredi à 08h00 UTC :

1. Télécharge le PHAR depuis la dernière release
2. Récupère les flux RSS configurés
3. Filtre les articles récents (jour précédent, ou vendredi-dimanche le lundi)
4. Génère des résumés en français via l'API Claude
5. Construit le HTML de la newsletter avec Twig
6. Publie une Discussion GitHub avec le label `newsletter`
7. Archive le fichier HTML dans `newsletters/`
8. Commit automatique de l'archive

## Archive des newsletters

Le répertoire `newsletters/` contient l'historique au format HTML (un fichier par jour : `YYYY-MM-DD.html`).
