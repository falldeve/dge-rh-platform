<?php

namespace App\Livewire\Concerns;

/**
 * Tri de colonnes réutilisable pour les listes de courriers (registre, archives, mes courriers).
 * Clic sur un en-tête = tri asc, re-clic = desc.
 */
trait SortableList
{
    public string $sortField = 'date_arrivee';
    public string $sortDir = 'desc';

    public function trier(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, string>  $autorises  colonnes triables
     */
    protected function appliquerTri($query, array $autorises)
    {
        $field = in_array($this->sortField, $autorises, true) ? $this->sortField : 'date_arrivee';
        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($field, $dir)->orderBy('id', 'desc');
    }
}
