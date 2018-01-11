<?php

namespace Spatie\Sitemap;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Tags\Tag;
use Spatie\Sitemap\Tags\Url;

class Sitemap implements Responsable
{
    /** @var array */
    protected $tags = [];

    public static function create(): self
    {
        return new static();
    }

    /**
     * @param string|\Spatie\Sitemap\Tags\Tag $tag
     *
     * @return $this
     */
    public function add($tag): self
    {
        if (is_string($tag)) {
            $tag = Url::create($tag);
        }

        if (! $this->hasUrl($tag->url)) {
            $this->tags[] = $tag;
        } else {
            $oldTag = $this->getUrl($tag->url);
            if ($tag->isNewer($oldTag)) {
                $this->update($oldTag, $tag);
            }
        }

        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param Url $oldTag
     * @param Url $newTag
     *
     * @return $this
     */
    public function update(Url $oldTag, Url $newTag)
    {
        array_splice($this->tags, array_search($oldTag, $this->tags), 1, [$newTag]);

        return $this;
    }

    /**
     * @param string $url
     *
     * @return \Spatie\Sitemap\Tags\Url|null
     */
    public function getUrl(string $url): ?Url
    {
        return collect($this->tags)->first(function (Tag $tag) use ($url) {
            return $tag->getType() === 'url' && $tag->url === $url;
        });
    }

    public function hasUrl(string $url): bool
    {
        return (bool) $this->getUrl($url);
    }

    public function render(): string
    {
        sort($this->tags);

        $tags = collect($this->tags)->unique('url')->filter();

        return view('laravel-sitemap::sitemap')
            ->with(compact('tags'))
            ->render();
    }

    public function writeToFile(string $path): self
    {
        file_put_contents($path, $this->render());

        return $this;
    }

    public function writeToDisk(string $disk, string $path): self
    {
        Storage::disk($disk)->put($path, $this->render());

        return $this;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return Response::make($this->render(), 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
