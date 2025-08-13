<?php

namespace Nirunfa\FlowProcessParser\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'id'                => $this->id,
            'parent'            => $this->when($this->pid > 0, $this->whenLoaded('parent', function () {
                return new CategoryResource($this->parent);
            })),
            'children'          => $this->whenLoaded('children', function () {
                return CategoryResource::collection($this->children);
            }),
            'name'              => $this->name,
             'description'       => $this->description,
            'order'             => $this->order_sort,
            'status'            => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
