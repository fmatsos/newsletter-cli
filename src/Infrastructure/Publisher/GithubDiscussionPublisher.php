<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Publisher;

use Akawaka\Newsletter\Domain\Port\NewsletterPublisherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GithubDiscussionPublisher implements NewsletterPublisherInterface
{
    private const GRAPHQL_ENDPOINT = 'https://api.github.com/graphql';
    private const DEFAULT_LABEL_COLOR = '0075ca';
    private const DEFAULT_LABEL_DESCRIPTION = 'Auto-generated newsletter digest';

    private const REPOSITORY_METADATA_QUERY = <<<'GRAPHQL'
        query RepositoryIdentifiers($owner: String!, $name: String!, $category: String!) {
            repository(owner: $owner, name: $name) {
                id
                discussionCategory(name: $category) {
                    id
                }
            }
        }
        GRAPHQL;

    private const LABEL_QUERY = <<<'GRAPHQL'
        query RepositoryLabel($owner: String!, $name: String!, $label: String!) {
            repository(owner: $owner, name: $name) {
                label(name: $label) {
                    id
                }
            }
        }
        GRAPHQL;

    private const CREATE_LABEL_MUTATION = <<<'GRAPHQL'
        mutation CreateLabel($repositoryId: ID!, $label: String!, $color: String!, $description: String!) {
            createLabel(input: {
                repositoryId: $repositoryId,
                name: $label,
                color: $color,
                description: $description
            }) {
                label {
                    id
                }
            }
        }
        GRAPHQL;

    private const CREATE_DISCUSSION_MUTATION = <<<'GRAPHQL'
        mutation CreateDiscussion($repositoryId: ID!, $categoryId: ID!, $title: String!, $body: String!) {
            createDiscussion(input: {
                repositoryId: $repositoryId,
                categoryId: $categoryId,
                title: $title,
                body: $body
            }) {
                discussion {
                    id
                    url
                }
            }
        }
        GRAPHQL;

    private const ADD_LABEL_MUTATION = <<<'GRAPHQL'
        mutation AddLabel($discussionId: ID!, $labelIds: [ID!]!) {
            addLabelsToLabelable(input: {
                labelableId: $discussionId,
                labelIds: $labelIds
            }) {
                clientMutationId
            }
        }
        GRAPHQL;

    private readonly HttpClientInterface $httpClient;
    private readonly string $repository;
    private readonly string $discussionCategory;
    private readonly string $token;
    private readonly string $owner;
    private readonly string $repositoryName;

    public function __construct(
        HttpClientInterface $httpClient,
        string $repository,
        string $discussionCategory = 'General',
        string $token = '',
    ) {
        $this->httpClient = $httpClient;
        $this->repository = $repository;
        $this->discussionCategory = $discussionCategory;
        $this->token = $token;
        [$this->owner, $this->repositoryName] = $this->splitRepository($repository);
    }

    #[\Override]
    public function publish(string $title, string $htmlContent, string $label): string
    {
        $this->ensureToken();

        $identifiers = $this->loadRepositoryIdentifiers();
        $repositoryId = $identifiers['repositoryId'];
        $categoryId = $identifiers['categoryId'];

        $labelId = $this->ensureLabelExists($repositoryId, $label);
        $bodyMarkdown = $this->wrapHtmlInMarkdown($htmlContent);
        $discussion = $this->createDiscussion($repositoryId, $categoryId, $title, $bodyMarkdown);
        $this->addLabel($discussion['id'], $labelId);

        return $discussion['url'];
    }

    private function ensureToken(): void
    {
        if ('' === trim($this->token)) {
            throw new \RuntimeException('GitHub token is required to publish discussions.');
        }
    }

    private function loadRepositoryIdentifiers(): array
    {
        $data = $this->sendGraphQl(self::REPOSITORY_METADATA_QUERY, [
            'owner' => $this->owner,
            'name' => $this->repositoryName,
            'category' => $this->discussionCategory,
        ]);

        $repository = $data['repository'] ?? null;
        if (!\is_array($repository) || empty($repository['id'])) {
            throw new \RuntimeException(sprintf('Failed to resolve repository %s.', $this->repository));
        }

        $category = $repository['discussionCategory'] ?? null;
        if (!\is_array($category) || empty($category['id'])) {
            throw new \RuntimeException(sprintf('Discussion category "%s" not found.', $this->discussionCategory));
        }

        return [
            'repositoryId' => $repository['id'],
            'categoryId' => $category['id'],
        ];
    }

    private function ensureLabelExists(string $repositoryId, string $label): string
    {
        $data = $this->sendGraphQl(self::LABEL_QUERY, [
            'owner' => $this->owner,
            'name' => $this->repositoryName,
            'label' => $label,
        ]);

        $existing = $data['repository']['label'] ?? null;
        if (\is_array($existing) && !empty($existing['id'])) {
            return $existing['id'];
        }

        $creation = $this->sendGraphQl(self::CREATE_LABEL_MUTATION, [
            'repositoryId' => $repositoryId,
            'label' => $label,
            'color' => self::DEFAULT_LABEL_COLOR,
            'description' => self::DEFAULT_LABEL_DESCRIPTION,
        ]);

        $created = $creation['createLabel']['label'] ?? null;
        if (!\is_array($created) || empty($created['id'])) {
            throw new \RuntimeException('Failed to create GitHub label.');
        }

        return $created['id'];
    }

    private function createDiscussion(string $repositoryId, string $categoryId, string $title, string $body): array
    {
        $data = $this->sendGraphQl(self::CREATE_DISCUSSION_MUTATION, [
            'repositoryId' => $repositoryId,
            'categoryId' => $categoryId,
            'title' => $title,
            'body' => $body,
        ]);

        $discussion = $data['createDiscussion']['discussion'] ?? null;
        if (!\is_array($discussion) || empty($discussion['id']) || empty($discussion['url'])) {
            throw new \RuntimeException('Failed to create GitHub discussion.');
        }

        return $discussion;
    }

    private function addLabel(string $discussionId, string $labelId): void
    {
        $this->sendGraphQl(self::ADD_LABEL_MUTATION, [
            'discussionId' => $discussionId,
            'labelIds' => [$labelId],
        ]);
    }

    private function sendGraphQl(string $document, array $variables): array
    {
        try {
            $response = $this->httpClient->request('POST', self::GRAPHQL_ENDPOINT, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->token),
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'AkawakaNewsletterCLI',
                ],
                'json' => [
                    'query' => $document,
                    'variables' => $variables,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('GitHub GraphQL request failed (%d).', $response->getStatusCode()));
            }

            $payload = $response->toArray(false);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('GitHub GraphQL request failed.', 0, $exception);
        }

        if (!\is_array($payload)) {
            throw new \RuntimeException('GitHub GraphQL response is invalid.');
        }

        if (!empty($payload['errors'])) {
            $messages = array_map(static fn (array $error): string => $error['message'] ?? 'unknown', $payload['errors']);
            throw new \RuntimeException(sprintf('GitHub GraphQL errors: %s', implode('; ', $messages)));
        }

        return $payload['data'] ?? [];
    }

    private function splitRepository(string $repository): array
    {
        $parts = explode('/', $repository, 2);

        if (2 !== \count($parts) || '' === trim($parts[0]) || '' === trim($parts[1])) {
            throw new \InvalidArgumentException('Repository must be provided in owner/repo format.');
        }

        return [$parts[0], $parts[1]];
    }

    private function wrapHtmlInMarkdown(string $html): string
    {
        return sprintf(
            "<!-- newsletter-digest -->\n\n<details>\n<summary>📧 Newsletter HTML (cliquer pour déplier)</summary>\n\n%s\n\n</details>\n\n---\n_Newsletter générée automatiquement par GitHub Actions._",
            $html,
        );
    }
}
