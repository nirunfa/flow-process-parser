<?php

namespace Nirunfa\FlowProcessParser\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nirunfa\FlowProcessParser\Models\NCategory;
use Nirunfa\FlowProcessParser\Models\NGroup;
use Nirunfa\FlowProcessParser\Models\NProcessDefinition;

class ProcessDefinitionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules ()
    {
        $rules = [];
        switch ($this->method()) {
            case 'GET':
                $rules = [
                    'name' => 'max:50',
                    'group_id'=>['nullable','integer'],
                    'category_id'=>['nullable','integer'],
                    'status'=>['nullable',Rule::in([NProcessDefinition::STATUS_DISABLE,NProcessDefinition::STATUS_ENABLE])],
                    'description'=>[]
                ];
                break;
            case 'POST':
                $rules = [
                    'name' => ['required',Rule::unique(NProcessDefinition::class, 'name')->ignore($this->route()->process_definition)],
                    'group_id'=>['nullable',Rule::exists(NGroup::class,'id')],
                    'category_id'=>['nullable',Rule::exists(NCategory::class,'id')],
                    'status'=>['required',Rule::in([NProcessDefinition::STATUS_DISABLE,NProcessDefinition::STATUS_ENABLE])],
                    'description'=>[],
                ];
                break;
        }
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages ()
    {
        return [
            'name.required' => '名称不能为空',
            'name.max' => '名称长度不能大于50',

        ];
    }
}