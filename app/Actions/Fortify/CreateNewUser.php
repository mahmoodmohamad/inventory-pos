<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\ShopRegistrationService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private ShopRegistrationService $registration) {}

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        /** @var array{shop_name: string, name: string, email: string, password: string} $validated */
        $validated = Validator::make($input, [
            'shop_name' => ['required', 'string', 'max:255'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->registration->register($validated);
    }
}
