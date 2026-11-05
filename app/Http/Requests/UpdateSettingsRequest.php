<?php

namespace App\Http\Requests;

use App\Services\SoundPackRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'volume' => ['sometimes', 'integer', 'between:0,100'],
            'pack' => ['sometimes', 'string', Rule::in(app(SoundPackRepository::class)->all(SoundPackRepository::KEYBOARD)->keys())],
            'mouse_enabled' => ['sometimes', 'boolean'],
            'mouse_pack' => ['sometimes', 'string', Rule::in(app(SoundPackRepository::class)->all(SoundPackRepository::MOUSE)->keys())],
            'key_up' => ['sometimes', 'boolean'],
            'pitch_variation' => ['sometimes', 'boolean'],
            'launch_at_login' => ['sometimes', 'boolean'],
        ];
    }
}
