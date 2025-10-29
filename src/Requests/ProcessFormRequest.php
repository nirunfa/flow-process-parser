<?php
namespace Nirunfa\FlowProcessParser\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nirunfa\FlowProcessParser\Models\NProcessForm;

class ProcessFormRequest extends FormRequest
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
                    'description' => 'max:200',
                    'name' => 'max:200',
                    'status' => [Rule::in([NProcessForm::STATUS_ENABLE,NProcessForm::STATUS_DISABLE])]
                ];
                break;
            case 'POST':
                $rules = [
                    'name' => ['required', 'max:255', Rule::unique(NProcessForm::class,'name')->ignore($this->route()->n_process_form)],
                    'description' => [],
                    'fields' => [],
                    'json_content' => ['required'],
                    'status' => ['required',  Rule::in([NProcessForm::STATUS_ENABLE,NProcessForm::STATUS_DISABLE])],
                ];
                break;
        }
        return $rules;

    }

    public function attributes()
    {
        return [
            'name' => '表单名称',
            'description' => '表单描述',
        ];
    }
}
