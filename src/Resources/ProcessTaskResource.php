<?php

namespace Nirunfa\FlowProcessParser\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Nirunfa\FlowProcessParser\Models\NProcessForm;

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
            'id'                => 'process_parser_'.$this->id,
            'name'              => $this->name,
            'description'       => $this->description,
            'status'            => $this->status,
            'assignees'     => $this->whenLoaded('assignees', function () {
                return $this->assignees->map(function ($assigneeItem) {
                    $assigneeItem->loadMissing('nodeApprover');
                    $approver = $assigneeItem->nodeApprover;
                    return [
                        'id' => $approver->id,
                        'name' => $approver->name,
                        'approver' => $approver->approver_ids,
                        'approver_name' => $approver->approver_names,
                        'approver_type' => $approver->approver_type,
                        'order' => $approver->order_sort,
                    ];
                });
            }),
            'form'     => $this->when($this->node_id > 0, $this->whenLoaded('node', function () {
                $this->node->loadMissing('form');
                $formInfo = $this->node->form->toArray();
                if($this->node->form instanceof NProcessForm){
                    $formInfo['fields'] = json_decode($this->node->form->json_content,true);
                    unset($formInfo['json_content']);
                }
                return $formInfo;
            })),
            'assignee_type'=>$this->when($this->node_id > 0, $this->whenLoaded('node', function () {
                $this->node->loadMissing('attr');
                return $this->node->attr->approve_type;
            })),
            'assignee_mode'=>$this->when($this->node_id > 0, $this->whenLoaded('node', function () {
                $this->node->loadMissing('attr');
                return $this->node->attr->approve_mode;
            })),
            'initiator_same'=>$this->when($this->node_id > 0, $this->whenLoaded('node', function () {
                $this->node->loadMissing('attr');
                return $this->node->attr->approver_same_initiator;
            })),
            'approver_empty'=>$this->when($this->node_id > 0, $this->whenLoaded('node', function () {
                $this->node->loadMissing('attr');
                return $this->node->attr->approver_empty;
            })),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
