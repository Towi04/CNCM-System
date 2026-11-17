<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class EditUserRequest extends FormRequest
{
     /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $user = $this->route('usuario');

        return [
            'nombres'           => 'required',
            'apellido_paterno'  => 'required',
            'email'             => "required|unique:users,email,{$user->id}",
            'password'          => ''
        ];
    }
}
