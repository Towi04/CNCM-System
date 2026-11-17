<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
    @if (trim($slot) === 'Laravel')
        <img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
    @else
        <img src="{{ imgToBase64(public_path('img/logo.png')) }}" alt="{{ $slot }}" style="width:250px;">
    @endif
</a>
</td>
</tr>
