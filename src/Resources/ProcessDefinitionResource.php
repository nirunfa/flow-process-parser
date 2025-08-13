<?php

namespace Nirunfa\FlowProcessParser\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProcessDefinitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                        => $this->id,
            'category_id'               => $this->category_id,
            'category'                  => $this->when($this->category_id > 0, $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            })),

            'group_id'               => $this->group_id,
            'group'                  => $this->when($this->group_id > 0, $this->whenLoaded('group', function () {
                return ($this->group);
            })),

            'name'                      => $this->name,

            'description'               => $this->description,
            'order_sort'                     => $this->order_sort,
            'status'                    => $this->status,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
