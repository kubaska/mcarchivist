<?php

namespace App\Services;

use App\API\DTO\AuthorDTO;
use App\API\DTO\ProjectDTO;
use App\Mca\ApiManager;
use App\Models\Author;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AuthorService
{
    public function __construct(private ApiManager $apiManager)
    {
    }

    public function archiveAuthors(Project $project, ProjectDTO $remote)
    {
        $authors = $remote->authors ?? $this->fetchAuthorsIfMissing($remote);

        if ($authors->isEmpty()) return;

        $localAuthors = [];
        /** @var AuthorDTO $author */
        foreach ($authors as $author) {
            $model = Author::firstOrCreate(
                ['platform' => $remote->platform, 'remote_id' => $author->remoteId],
                ['name' => $author->name, 'avatar' => $author->avatarUrl ?? null]
            );

            if ($author->role) $localAuthors[$model->id] = ['role' => $author->role];
            else $localAuthors[] = $model->id;
        }

        Log::debug(sprintf('Syncing authors for project %s.', $project->name), [$localAuthors]);
        $project->authors()->sync($localAuthors);
    }

    protected function fetchAuthorsIfMissing(ProjectDTO $remote): Collection
    {
        $api = $this->apiManager->get($remote->platform);
        return $api->getProjectAuthors($remote->id)->getData();
    }
}
