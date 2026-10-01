<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function credentials(): array
    {
        return [
            'email' => $this->string('email')->lower()->trim()->toString(),
            'password' => $this->string('password')->toString(),
        ];
    }

    /**
     * Rate-limit key is per email+IP so one attacker can't lock out an
     * arbitrary victim account by hammering just their email.
     */
    public function throttleKey(): string
    {
        return \Illuminate\Support\Str::lower($this->input('email')).'|'.$this->ip();
    }
}
