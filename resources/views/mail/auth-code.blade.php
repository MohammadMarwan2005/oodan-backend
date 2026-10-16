@if ($purpose === \App\Enums\AuthCodePurpose::VerifyEmail)
رمز تأكيد بريدك في «عوداً عوداً»:
@else
رمز إعادة تعيين كلمة المرور في «عوداً عوداً»:
@endif

{{ $code }}

ينتهي الرمز خلال {{ $minutes }} دقيقة. إن لم تطلبه فتجاهل هذه الرسالة.

—

Your «عوداً عوداً» code is {{ $code }}. It expires in {{ $minutes }} minutes.
If you didn't request it, ignore this email.
