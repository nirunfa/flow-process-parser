<?php
namespace Nirunfa\FlowProcessParser\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nirunfa\FlowProcessParser\Models\NProcessInstance;

class ProcessInstanceRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules ()
    {
        $rules = [
            'title' => 'required|max:50',
            'code' => 'max:200',
            'user_name' => [],
            'status' => [
                Rule::in ([
                    NProcessInstance::STATUS_UNSTARTED,
                    NProcessInstance::STATUS_PROCESSING,
                    NProcessInstance::STATUS_COMPLETED,
                    NProcessInstance::STATUS_REVOKED,
                    NProcessInstance::STATUS_ABANDONED,
                ])
            ],
            'is_archive' => [
                Rule::in ([
                    NProcessInstance::IS_ARCHIVE_NO,
                    NProcessInstance::IS_ARCHIVE_YES,
                ])
            ]

        ];
        switch ($this->method()) {
            case 'GET':
                $rules = [
                    'title' => 'max:50',
                    'code' => 'max:200',
                    'start_time' => 'string',
                    'end_time' => 'string',
                    'status' => [
                        Rule::in ([
                            NProcessInstance::STATUS_UNSTARTED,
                            NProcessInstance::STATUS_PROCESSING,
                            NProcessInstance::STATUS_COMPLETED,
                            NProcessInstance::STATUS_REVOKED,
                            NProcessInstance::STATUS_ABANDONED,
                        ])
                    ],
                    'is_archive' => [
                        Rule::in ([
                            NProcessInstance::IS_ARCHIVE_NO,
                            NProcessInstance::IS_ARCHIVE_YES,
                        ])
                    ],
                    'apply_user'=>[]
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
            'title.required' => '名称不能为空',
            'title.max' => '名称长度不能大于50',

        ];
    }
}
