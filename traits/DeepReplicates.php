<?php namespace Acorn\Traits;

trait DeepReplicates
{
    /*
    public function replicate(?array $except = null)
    {
        // Replicate relations recursively also
        $copy = parent::replicate($except);
        $copy->push();

        // TODO: Relations will not be loaded yet
        foreach ($this->getRelations() as $relation => $entries) {
            foreach($entries as $entry) {
                $e = $entry->replicate($except);
                if ($e->push()) {
                    $clone->{$relation}()->save($e);
                }
            }
        }
    }
    */
}
