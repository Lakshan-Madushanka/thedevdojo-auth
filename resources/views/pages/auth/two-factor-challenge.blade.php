<?php

use App\Models\User;
use function Laravel\Folio\{middleware, name};
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Login;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use PragmaRX\Google2FA\Google2FA;
use Devdojo\Auth\Traits\HasConfigs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

middleware(['two-factor-challenged', 'throttle:5,1']);
name('auth.two-factor-challenge');

new class extends Component
{
    use HasConfigs;
    
    public $recovery = false;
    public $google2fa;

    public $auth_code;
    public $recovery_code;

    public function mount()
    {
        $this->loadConfigs();
        $this->recovery = false;
    }

    public function switchToRecovery()
    {
        $this->recovery = !$this->recovery;
        if($this->recovery){
            $this->js("setTimeout(function(){ console.log('made'); window.dispatchEvent(new CustomEvent('focus-auth-2fa-recovery-code', {})); }, 10);");
        } else {
            $this->js("setTimeout(function(){ window.dispatchEvent(new CustomEvent('focus-auth-2fa-auth-code', {})); }, 10);");
        }
        return;
    }

    // TODO - Refactor the submitCode functionality into it's own trait so we can use this functionality here and user/two-factor-authenticaiton.blade.php

     #[On('submitCode')] 
    public function submitCode($code)
    {
        if(empty($code) || strlen($code) < 5){
            dd('show validation error');
            return;
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($this->secret, $code);


        if($valid){
            $user = User::find(session()->get('login.id'));
        
            Auth::login($user);
            event(new Login(auth()->guard('web'), User::where('email', $this->email)->first(), true));
            return redirect()->intended('/');
        } else {

        }

    }

    public function submit_auth_code()
    {
        $google2fa = new Google2FA();
        //$this->verify(auth()->user()->two_factor_secret, $this->auth_code, $google2fa);
        $valid = $google2fa->verifyKey(decrypt(auth()->user()->two_factor_secret), $this->auth_code);

        if ($valid) {
            dd('Valid!');
        } else {
            dd('Failed');
        }
    }

    public function submit_recovery_code(){
        $valid = in_array($this->recovery_code, auth()->user()->two_factor_recovery_codes);

        if ($valid) {
            dd('valid yo!');
        } else {
            dd('not valid');
        }
    }

    /*private function verify($secret, $code, $google2fa)
    {
        $cachedTimestampKey = 'auth.2fa_codes.'.md5($code);

        if (is_int($customWindow = config('fortify-options.two-factor-authentication.window'))) {
            $google2fa->setWindow($customWindow);
        }

        $timestamp = $google2fa->verifyKeyNewer(
            $secret, $code, Cache::get($cachedTimestampKey)
        );

        if ($timestamp !== false) {
            if ($timestamp === true) {
                $timestamp = $google2fa->getTimestamp();
            }

            optional($cache)->put($cachedTimestampKey, $timestamp, ($google2fa->getWindow() ?: 1) * 60);

            return true;
        }

        return false;
    }*/
}

?>

<x-auth::layouts.app title="{{ config('devdojo.auth.language.twoFactorChallenge.page_title') }}">
    @volt('auth.twofactorchallenge')
        <x-auth::elements.container>
            <div x-data x-on:code-input-complete.window="console.log(event); $dispatch('submitCode', [event.detail.code])" class="relative w-full h-auto">
                @if(!$recovery)
                    <x-auth::elements.heading 
                        :text="($language->twoFactorChallenge->headline_auth ?? 'No Heading')"
                        :description="($language->twoFactorChallenge->subheadline_auth ?? 'No Description')"
                        :show_subheadline="($language->twoFactorChallenge->show_subheadline_auth ?? false)" />
                @else
                    <x-auth::elements.heading 
                        :text="($language->twoFactorChallenge->headline_recovery ?? 'No Heading')"
                        :description="($language->twoFactorChallenge->subheadline_recovery ?? 'No Description')"
                        :show_subheadline="($language->twoFactorChallenge->show_subheadline_recovery ?? false)" />
                @endif

                <div class="mt-5 space-y-5">

                    @if(!$recovery)
                        <div class="relative">
                            <x-auth::elements.input-code wire:model="auth_code" id="auth-input-code" digits="6" eventCallback="code-input-complete" type="text" label="Code" />
                        </div>
                        @error('auth_code')
                            <p>Incorrect Auth Code</p>
                        @enderror
                        <x-auth::elements.button rounded="md" submit="true" wire:click="submitCode(document.getElementById('auth-input-code').value)">Continue</x-auth::elements.button>
                    @else
                        <div class="relative">
                            <x-auth::elements.input label="Recovery Code" type="text" wire:model="recovery_code" id="auth-2fa-recovery-code" required />
                        </div>
                        <x-auth::elements.button rounded="md" submit="true" wire:click="submitRecoveryCode">Continue</x-auth::elements.button>
                    @endif

                    
                </div>

                <div class="mt-5 space-x-0.5 text-sm leading-5 text-left" style="color:{{ config('devdojo.auth.appearance.color.text') }}">
                    <span class="opacity-[47%]">or you can </span>
                    <span class="font-medium underline opacity-60 cursor-pointer" wire:click="switchToRecovery" href="#_">
                        @if(!$recovery)
                            <span>login using a recovery code</span>
                        @else
                            <span>login using an authentication code</span>
                        @endif
                    </span>
                </div>
            </div>
        </x-auth::elements.container>
    @endvolt
</x-auth::layouts.app>
