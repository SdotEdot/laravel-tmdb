<?php

namespace Astrotomic\Tmdb\Eloquent\Builders;

use Astrotomic\Tmdb\Models\Model;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @method \Astrotomic\Tmdb\Models\Model findOrNew(int $id, string[] $columns = ['*'])
 * @method \Astrotomic\Tmdb\Models\Model findOrFail(int|int[]|\Illuminate\Contracts\Support\Arrayable $id, string[] $columns = ['*'])
 */
abstract class Builder extends EloquentBuilder
{
    /**
     * @param int|int[]|\Illuminate\Contracts\Support\Arrayable $id
     * @param string[] $columns
     *
     * @return \Astrotomic\Tmdb\Models\Model|\Illuminate\Database\Eloquent\Collection|null
     */
    public function find($id, $columns = ['*']): Model|Collection|null
    {
        return DB::transaction(function () use ($id, $columns): Model|Collection|null {
            if (is_array($id) || $id instanceof Arrayable) {
                return $this->findMany($id, $columns);
            }

            $model = $this->whereKey($id)->first($columns);

            if ($model instanceof Model) {
                return $model;
            }

            return $this->createFromTmdb($id);
        });
    }

    /**
     * @param int[]|\Illuminate\Contracts\Support\Arrayable $ids
     * @param string[] $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findMany($ids, $columns = ['*']): Collection
    {
        return DB::transaction(function () use ($ids, $columns): Collection {
            $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

            if (empty($ids)) {
                return $this->model->newCollection();
            }

            $models = $this->whereKey($ids)->get($columns);

            if ($models->count() === count($ids)) {
                return $models;
            }

            return $models->merge(
                collect($ids)
                    ->reject(fn (int $id): bool => $models->contains($id))
                    ->map(fn (int $id): ?Model => $this->createFromTmdb($id))
                    ->filter()
            );
        });
    }

    public function createFromTmdb(int $id): ?Model
    {
        $model = $this->newModelInstance(['id' => $id]);

        if (! $model->updateFromTmdb()) {
            return null;
        }

        return $model;
    }
}
