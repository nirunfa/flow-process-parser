<?php
namespace Nirunfa\FlowProcessParser\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nirunfa\FlowProcessParser\Models\NGroup;

class GroupRequest extends FormRequest
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
                    'description' => 'max:100',
                    'name' => 'max:50',
                ];
                break;
            case 'POST':
                $rules = [
                    'name' => ['required', 'max:50', Rule::unique(NGroup::class,'name')->ignore($this->route()->group)],
                    'description' => [],
                    'order_sort' => ['nullable', 'numeric'],
                    'status' => ['required',  Rule::in([NGroup::STATUS_ENABLE,NGroup::STATUS_DISABLE])],
                ];
                break;
        }
        return $rules;

    }

    public function attributes()
    {
        return [
            'name' => '分组名称',
            'description' => '分组描述',
            'order_sort' => '分组排序',
        ];
    }
}
