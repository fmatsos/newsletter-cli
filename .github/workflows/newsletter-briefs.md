---
description: |
  Generate newsletter article briefs using GitHub Copilot.
  Collects articles from RSS/Atom feeds via Newsletter CLI, generates French summaries
  for each article using AI, then builds and publishes the newsletter with generated briefs.

on:
  schedule:
    - cron: "0 8 * * 1-5"
  workflow_dispatch:

engine:
  id: copilot
  model: gpt-4.1

timeout-minutes: 15

permissions:
  contents: "read"
  discussions: "read"
  issues: "read"
  models: "read"
  pull-requests: "read"

runs-on: "ubuntu-latest"

steps:
  - name: Preinstall PHP for Newsletter CLI
    run: |
      sudo apt-get update
      sudo apt-get install -y php-cli php-zip
      php -v
  - name: Download Newsletter CLI PHAR
    env:
      NEWSLETTER_PHAR_URL: https://github.com/fmatsos/newsletter-cli/releases/latest/download/newsletter.phar
      GITHUB_TOKEN: ${{ github.token }}
    run: |
      set -eo pipefail
      mkdir -p ./bin
      curl -L --fail \
        -H "Authorization: Bearer ${GITHUB_TOKEN}" \
        -H "Accept: application/octet-stream" \
        -H "User-Agent: gh-aw" \
        -o ./bin/newsletter.phar \
        "${NEWSLETTER_PHAR_URL}" \
      || curl -L --fail \
        -H "User-Agent: gh-aw" \
        -o ./bin/newsletter.phar \
        "${NEWSLETTER_PHAR_URL}"
      chmod +x ./bin/newsletter.phar
  - name: Collect articles with Newsletter CLI
    run: |
      set -eo pipefail
      php ./bin/newsletter.phar build \
        --config=config/newsletter.yaml \
        --export-articles=articles.json \
        -v

network:
  allowed:
    - defaults
    - github
    - linux-distros

safe-outputs:
  create-discussion:
    category: "newsletters"
    expires: false

tools:
  bash: true
---

# Newsletter Brief Generation

You are a French tech editorial assistant. Your task is to generate concise French summaries for tech news articles collected by the Newsletter CLI tool.

## Step 0 — Verify PHP and Newsletter CLI

Confirm PHP with Phar support is available (preinstalled via workflow steps), and ensure `./bin/newsletter.phar` exists:

```bash
set -eo pipefail
php -v
./bin/newsletter.phar --version
```

## Step 1 — Collect articles

Articles are pre-collected in `articles.json` via workflow steps. Rerun the Newsletter CLI if you need to refresh the feed data:

```bash
php ./bin/newsletter.phar \
  --config=config/newsletter.yaml \
  --export-articles=articles.json \
  -v
```

## Step 2 — Generate briefs

Read the exported `articles.json` file. It contains an array of articles, each with:
- `url`: the article URL (used as key)
- `title`: the article title
- `description`: the original feed description
- `source`: the source name
- `category`: the category ID

For **each article**, write a concise French summary (2-3 sentences, ~50 words max):
- Be informative and factual
- Use professional, natural French
- Explain what the article announces or covers
- Do not exceed 3 sentences

Write the results to `briefs.json` as a JSON object mapping each article URL to its French summary:

```json
{
  "https://example.com/article-1": "Résumé en français de l'article 1.",
  "https://example.com/article-2": "Résumé en français de l'article 2."
}
```

Use this bash command to write the file:

```bash
cat > briefs.json << 'BRIEFS_EOF'
{
  ... your generated briefs here ...
}
BRIEFS_EOF
```

## Step 3 — Build the newsletter

Run the Newsletter CLI again, this time importing the generated briefs:

```bash
php ./bin/newsletter.phar build \
  --config=config/newsletter.yaml \
  --templates=templates \
  --repository=${{ github.repository }} \
  --discussion-category=newsletters \
  --import-briefs=briefs.json \
  --archive-dir=newsletters \
  --output=newsletter-output.html \
  -v
```

## Step 4 — Commit the archive

If the newsletter was built successfully, commit the archived newsletter:

```bash
git config user.name "github-actions[bot]"
git config user.email "github-actions[bot]@users.noreply.github.com"
git add newsletters/
git diff --cached --quiet || git commit -m "Archive newsletter $(date +%Y-%m-%d)"
git push
```
