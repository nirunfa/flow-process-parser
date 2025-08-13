<?php
namespace Nirunfa\FlowProcessParser\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Nirunfa\FlowProcessParser\Models\NCategory;

class CategoryRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules ()
    {
        $rules = [
            'pid' => ['integer|exclude_if:pid,0|different:id',Rule::exists(NCategory::class,'id')],
            'name' => ['required'],
            'order_sort' => ['nullable','numeric'],
            'description' => [],
            'status' => [
                'required',
                Rule::in ([NCategory::STATUS_DISABLE, NCategory::STATUS_ENABLE])
            ]
        ];
        switch ($this->method()) {
            case 'GET':
                $rules = [
                    'pid' => 'integer',
                    'name' => 'max:20',
                    'description' => 'max:255',
                    'status' => [
                        Rule::in ([NCategory::STATUS_DISABLE, NCategory::STATUS_ENABLE])
                    ]
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
        ];
    }
}
