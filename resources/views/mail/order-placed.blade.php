<x-mail::message>
# Thanks, {{ $name }}

Your order **{{ $reference }}** is confirmed. Here's what's coming:

@foreach ($lines as $line)
- {{ $line['name'] }} &times;{{ $line['quantity'] }}
@endforeach

Postage: {{ $currency }} {{ $postage }}
**Total: {{ $currency }} {{ $total }}**

You can check on it any time — you'll need this order number and the email
address you ordered with.

<x-mail::button :url="$trackUrl">
Track this order
</x-mail::button>

Thanks for shopping with us.
</x-mail::message>
