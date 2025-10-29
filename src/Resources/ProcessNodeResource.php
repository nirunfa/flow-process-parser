<?php

namespace Nirunfa\FlowProcessParser\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProcessNodeResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

}