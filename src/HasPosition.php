<?php

namespace Nevadskiy\Position;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait HasPosition
{
    /**
     * Boot the position trait.
     */
    public static function bootHasPosition(): void
    {
        self::creating(static function (self $model) {
            $model->setPositionIfMissing();
        });
    }

    /**
     * Get the name of the "position" column.
     */
    public function getPositionColumn(): string
    {
        return 'position';
    }

    /**
     * Get the initial position value.
     */
    public function getInitPosition(): int
    {
        return 0;
    }

    /**
     * Set the next position value to the model if it is missing.
     */
    public function setPositionIfMissing(): void
    {
        if (is_null($this->getPosition())) {
            $this->setNextPosition();
        }
    }

    /**
     * Get the position value of the model.
     */
    public function getPosition(): ?int
    {
        return $this->getAttribute($this->getPositionColumn());
    }

    /**
     * Set the position to the given value.
     */
    public function setPosition(int $position): Model
    {
        return $this->setAttribute($this->getPositionColumn(), $position);
    }

    /**
     * Set the next position value to the model.
     */
    public function setNextPosition(): Model
    {
        return $this->setPosition($this->getNextPositionInSequence());
    }

    /**
     * Determine the next position value in the model sequence.
     */
    protected function getNextPositionInSequence(): int
    {
        $maxPosition = $this->getMaxPositionInSequence();

        if (is_null($maxPosition)) {
            return $this->getInitPosition();
        }

        return $maxPosition + 1;
    }

    /**
     * Get the max position value in the model sequence.
     */
    protected function getMaxPositionInSequence(): ?int
    {
        return $this->newPositionQuery()->max($this->getPositionColumn());
    }

    /**
     * Get a new position query.
     */
    protected function newPositionQuery(): Builder
    {
        return $this->newQuery();
    }

    /**
     * Scope a query to sort models by positions.
     */
    public function scopeOrderByPosition(Builder $query): Builder
    {
        return $query->orderBy($this->getPositionColumn());
    }

    /**
     * Scope a query to sort models by inverse positions.
     */
    public function scopeOrderByInversePosition(Builder $query): Builder
    {
        return $query->orderBy($this->getPositionColumn(), 'desc');
    }

    /**
     * Move the model to the new position.
     */
    public function move(int $newPosition): bool
    {
        $oldPosition = $this->getPosition();

        if ($oldPosition === $newPosition) {
            return false;
        }

        if ($newPosition < $oldPosition) {
            $this->shiftModelsToEnd($newPosition, $oldPosition);
        } else if ($newPosition > $oldPosition) {
            $this->shiftModelsToStart($oldPosition, $newPosition);
        }

        $this->setPosition($newPosition);

        return $this->save();
    }

    /**
     * Shift all models that are between the given positions to the end of the sequence.
     * TODO: find a way to extract into query builder
     */
    protected function shiftModelsToEnd(int $startPosition, int $stopPosition = null, int $shift = 1): int
    {
        return $this->newPositionQuery()
            ->where($this->getPositionColumn(), '>=', $startPosition)
            ->when($stopPosition, function (Builder $query) use ($stopPosition) {
                $query->where($this->getPositionColumn(), '<=', $stopPosition);
            })
            ->increment($this->getPositionColumn(), $shift);
    }

    /**
     * Shift all models that are between the given positions to the beginning of the sequence.
     * TODO: find a way to extract into query builder
     */
    protected function shiftModelsToStart(int $startPosition, int $stopPosition = null, int $shift = 1): int
    {
        return $this->newPositionQuery()
            ->where($this->getPositionColumn(), '>=', $startPosition)
            ->when($stopPosition, function (Builder $query) use ($stopPosition) {
                $query->where($this->getPositionColumn(), '<=', $stopPosition);
            })
            ->decrement($this->getPositionColumn(), $shift);
    }

    /**
     * Arrange the models by the given ordered IDs.
     */
    public function arrangeByIds(array $ids, int $startPosition = null): void
    {
        $startPosition = is_null($startPosition) ? $this->getInitPosition() : $startPosition;

        foreach ($ids as $position => $id) {
            $this->newPositionQuery()
                ->whereKey($id)
                ->update([
                    $this->getPositionColumn() => $startPosition + $position
                ]);
        }
    }
}