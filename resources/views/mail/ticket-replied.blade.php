<x-mail::message>
# Hi {{ $name }}

We've replied to your ticket **{{ $ticketNo }}** — *{{ $title }}*.

<x-mail::panel>
{{ $message }}
</x-mail::panel>

Reply to this email if you need anything else, or pick the conversation back up
here:

<x-mail::button :url="$supportUrl">
View this ticket
</x-mail::button>

Thanks for your patience.
</x-mail::message>
