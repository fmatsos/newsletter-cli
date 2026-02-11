---
description: |
  Generate newsletter article briefs using GitHub Copilot.
  Collects articles from RSS/Atom feeds via Newsletter CLI, generates French summaries
  for each article using AI, then builds and publishes the newsletter with generated briefs.

on:
  schedule: weekdays
  workflow_dispatch:

engine:
  model: gpt-4.1

timeout-minutes: 15

permissions:
  contents: read
  discussions: write
  issues: write
  models: read

network: defaults

safe-outputs:
  create-discussion:
    title-prefix: "newsletter-briefs"
    category: "General"
    max: 1

tools:
  bash: true

---

# Newsletter Brief Generation

You are a French tech editorial assistant. Your task is to generate concise French summaries for tech news articles collected by the Newsletter CLI tool.

## Step 0 — Setup

Download the latest PHAR release and ensure config exists:

```bash
gh release download --repo ${{ github.repository }} --pattern 'newsletter.phar' --dir ./bin
chmod +x ./bin/newsletter.phar

# Ensure config exists
if [ ! -f config/newsletter.yaml ]; then
  cp config/newsletter.yaml.dist config/newsletter.yaml
fi
```

## Step 1 — Collect articles

Run the Newsletter CLI to collect articles from RSS/Atom feeds and export them to a JSON file:

```bash
php ./bin/newsletter.phar newsletter:build \
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
php ./bin/newsletter.phar newsletter:build \
  --config=config/newsletter.yaml \
  --templates=templates \
  --repository=${{ github.repository }} \
  --discussion-category=General \
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
git diff --cached --quiet || git commit -m "📰 Archive newsletter $(date +%Y-%m-%d)"
git push
```
