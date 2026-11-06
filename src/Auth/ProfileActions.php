<?php

namespace Xelqun\Panels\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionSet;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Layouts\Text;
use Xelqun\Panels\Contracts\HasAppAuthentication;
use Xelqun\Panels\Panels;

/**
 * The profile page's two-factor buttons. Only the signed-in user can reach their
 * own record, whatever a token says.
 *
 * @internal
 */
final class ProfileActions extends ActionSet
{
    public const string RECOVERY_CODES = 'xelqun-panels.recovery-codes';

    private const string PENDING_SECRET = 'xelqun-panels.pending-secret';

    public function actions(): array
    {
        $record = $this->getRecord();

        return $record instanceof HasAppAuthentication && $record->getAppAuthenticationSecret() !== null
            ? [$this->regenerateAction(), $this->disableAction()]
            : [$this->enableAction()];
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return $user instanceof HasAppAuthentication
            && $record !== null
            && $user->getAuthIdentifier() === $record->getKey()
            && (bool) app(Panels::class)->current()?->hasMultiFactorAuthentication();
    }

    public function model(): string
    {
        return auth()->user()::class;
    }

    public function resolveRecord(int|string $key): Model
    {
        $user = auth()->user();

        abort_unless($user instanceof Model && (string) $user->getKey() === (string) $key, 404);

        return $user;
    }

    public function getId(): string
    {
        return 'profile-two-factor';
    }

    private function enableAction(): Action
    {
        return Action::make('enable')
            ->label(__('xelqun-panels::auth.mfa.enable'))
            ->icon('shield')
            ->color('gray')
            ->authorize(true)
            ->modalDescription(__('xelqun-panels::auth.mfa.enable_description'))
            ->modalSubmitActionLabel(__('xelqun-panels::auth.mfa.enable_submit'))
            ->schema(fn (): array => [
                Text::make(fn (): HtmlString => $this->setupLink()),
                Text::make(__('xelqun-panels::auth.mfa.key', ['key' => $this->pendingSecret()]))->fontFamily('mono'),
                TextInput::make('code')->label(__('xelqun-panels::auth.mfa.code'))->required()->maxLength(6)->autocomplete('one-time-code'),
            ])
            ->successNotificationTitle(__('xelqun-panels::auth.mfa.enabled'))
            ->successRedirectUrl(fn (): string => $this->profileUrl())
            ->action(function (array $data): void {
                $user = $this->user();
                $secret = $this->pendingSecret();

                if (! AppAuthentication::verifyTotp($user, $secret, (string) $data['code'])) {
                    throw ValidationException::withMessages(['data.code' => __('xelqun-panels::auth.mfa.invalid')]);
                }

                session()->forget(self::PENDING_SECRET);
                $this->saveWithNewCodes($secret);
            });
    }

    private function regenerateAction(): Action
    {
        return Action::make('regenerate')
            ->label(__('xelqun-panels::auth.mfa.regenerate'))
            ->color('gray')
            ->authorize(true)
            ->modalDescription(__('xelqun-panels::auth.mfa.regenerate_description'))
            ->schema(fn (): array => [$this->passwordField()])
            ->successNotificationTitle(__('xelqun-panels::auth.mfa.regenerated'))
            ->successRedirectUrl(fn (): string => $this->profileUrl())
            ->action(fn () => $this->saveWithNewCodes((string) $this->user()->getAppAuthenticationSecret()));
    }

    private function disableAction(): Action
    {
        return Action::make('disable')
            ->label(__('xelqun-panels::auth.mfa.disable'))
            ->color('danger')
            ->authorize(true)
            ->modalDescription(__('xelqun-panels::auth.mfa.disable_description'))
            ->schema(fn (): array => [$this->passwordField()])
            ->successNotificationTitle(__('xelqun-panels::auth.mfa.disabled'))
            ->successRedirectUrl(fn (): string => $this->profileUrl())
            ->action(fn () => $this->user()->saveAppAuthentication(null));
    }

    private function passwordField(): TextInput
    {
        return TextInput::make('current_password')
            ->label(__('xelqun-panels::auth.password'))
            ->password()
            ->required()
            ->autocomplete('current-password')
            ->rules(['current_password:'.app(Panels::class)->current()->getAuthGuard()]);
    }

    private function saveWithNewCodes(string $secret): void
    {
        $codes = AppAuthentication::generateRecoveryCodes();

        $this->user()->saveAppAuthentication($secret, array_map(AppAuthentication::hashRecoveryCode(...), $codes));
        session()->flash(self::RECOVERY_CODES, $codes);
    }

    /**
     * Kept in the session, because the modal is rebuilt on every request and the
     * code must match the key on screen. It only reaches the user once it's proved.
     */
    private function pendingSecret(): string
    {
        return session()->remember(self::PENDING_SECRET, Totp::generateSecret(...));
    }

    private function setupLink(): HtmlString
    {
        $panel = app(Panels::class)->current();
        $uri = Totp::uri($this->pendingSecret(), $panel->getBrandName(), (string) data_get($this->user(), 'email'));

        return new HtmlString('<a href="'.e($uri).'" class="text-xq-primary underline">'.e(__('xelqun-panels::auth.mfa.open_app')).'</a>');
    }

    private function profileUrl(): string
    {
        return route(app(Panels::class)->current()->routeName('auth.profile'));
    }

    private function user(): HasAppAuthentication
    {
        $user = auth()->user();

        abort_unless($user instanceof HasAppAuthentication, 403);

        return $user;
    }
}
