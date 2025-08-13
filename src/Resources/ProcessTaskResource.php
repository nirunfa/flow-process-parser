<?php

namespace Nirunfa\FlowProcessParser\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProcessTaskResource extends JsonResource
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
            'name'              => $this->name,
            'status'            => $this->status,
            'assignees'     => $this->when($this->node_id > 0, $this->whenLoaded('node', function () {
                $this->node->loadMissing('approvers');
                $approvers = $this->node->approvers;
                return $approvers->map(function ($approver) {
                    return [
                        'id' => $approver->id,
                        'name' => $approver->name,
                        'approver' => $approver->approver_ids,
                        'approver_name' => $approver->approver_names,
                        'order' => $approver->order_sort,
                    ];
                });
            })),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
