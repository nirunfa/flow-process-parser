<?php

namespace Nirunfa\FlowProcessParser\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProcessInstanceResource extends JsonResource
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
            'title'               => $this->title,
            'ver'               => $this->ver,
            'code'               => $this->code,
            'status'               => $this->status,
            'is_archived'               => $this->is_archived,
            'design'                  => $this->when($this->design_id > 0, $this->whenLoaded('design', function () {
                return new ProcessDesignResource($this->design);
            })),

            'applier_id'               => $this->initiator_id,
            'applier'                  => $this->when($this->initiator_id > 0, $this->whenLoaded('applier', function () {
                return ($this->applier);
            })),

            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration' => $this->duration,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
