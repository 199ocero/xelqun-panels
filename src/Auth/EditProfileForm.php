<?php

namespace Xelqun\Panels\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use LogicException;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\Notifications\EmailChangeRequested;
use Xelqun\Panels\Auth\Notifications\VerifyEmailChange;
use Xelqun\Panels\Panels;

/**
 * The signed-in user's own details. Whatever key a token holds, the record is
 * always the current user, so nobody can edit anyone else here.
 *
 * @api
 */
class EditProfileForm extends Form
{
    public function model(): string
    {
        return $this->resolveUser()::class;
    }

    public function resolveRecord(int|string $key): Model
    {
        return $this->resolveUser();
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return $user !== null;
    }

    public function schema(): array
    {
        $guard = auth()->getDefaultDriver();
        $user = $this->resolveUser();

        return [
            Section::make(__('xelqun-panels::auth.profile.details'))->schema([
                TextInput::make('name')
                    ->label(__('xelqun-panels::auth.name'))
                    ->required()
                    ->maxLength(255)
                    ->autocomplete('name'),
                TextInput::make('email')
                    ->label(__('xelqun-panels::auth.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->autocomplete('username')
                    ->rules([Rule::unique($user->getTable(), 'email')->ignore($user)]),
            ]),
            Section::make(__('xelqun-panels::auth.profile.password'))->schema([
                TextInput::make('password')
                    ->label(__('xelqun-panels::auth.new_password'))
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->rules([Password::defaults()])
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                TextInput::make('password_confirmation')
                    ->label(__('xelqun-panels::auth.confirm_password'))
                    ->password()
                    ->autocomplete('new-password')
                    ->requiredWith('password')
                    ->same('password')
                    ->saved(false),
                TextInput::make('current_password')
                    ->label(__('xelqun-panels::auth.current_password'))
                    ->password()
                    ->autocomplete('current-password')
                    ->requiredWith('password')
                    ->requiredUnless('email', (string) $user->getAttribute('email'))
                    ->rules(["current_password:{$guard}"])
                    ->saved(false),
            ]),
        ];
    }

    /**
     * A new address hasn't been proven, and a new password ends "remember me" on
     * every other device.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function mutateBeforeSave(array $attributes): array
    {
        $user = $this->resolveUser();
        $isChangingEmail = ($attributes['email'] ?? $user->getAttribute('email')) !== $user->getAttribute('email');

        if ($isChangingEmail && app(Panels::class)->current()?->hasEmailChangeVerification()) {
            // The address stays as it is until the link sent to the new one is opened.
            $this->pendingEmail = $attributes['email'];
            unset($attributes['email']);
        } elseif ($isChangingEmail) {
            $attributes['email_verified_at'] = null;
        }

        if (isset($attributes['password'])) {
            $attributes['remember_token'] = Str::random(60);
        }

        return $attributes;
    }

    private ?string $pendingEmail = null;

    public function afterCommit(Model $record, array $data): void
    {
        if ($this->pendingEmail !== null) {
            $panel = app(Panels::class)->current();
            $url = URL::temporarySignedRoute($panel->routeName('auth.email-change.verify'), now()->addHour(), ['id' => $record->getKey(), 'email' => $this->pendingEmail, 'from' => hash('sha256', (string) $record->getAttribute('email'))]);

            NotificationFacade::route('mail', $this->pendingEmail)->notify(new VerifyEmailChange($url));
            NotificationFacade::route('mail', (string) $record->getAttribute('email'))->notify(new EmailChangeRequested($this->pendingEmail));
            Notification::make()->title(__('xelqun-panels::auth.email_change.sent', ['email' => $this->pendingEmail]))->info()->send();
        }

        if ($record->wasChanged('email') && $record instanceof MustVerifyEmail) {
            $record->sendEmailVerificationNotification();
        }
    }

    private function resolveUser(): Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : throw new LogicException('The profile form needs a signed-in Eloquent user.');
    }
}
