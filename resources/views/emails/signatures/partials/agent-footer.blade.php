@if(!empty($agentFooter))
    <div style="border-top: 1px solid #e0e0e0; margin-top: 25px; padding-top: 15px;">
        <p style="color: #666; font-size: 12px; margin: 0;">
            <strong>{{ $agentFooter['name'] }}</strong><br>
            Home Finders Coastal<br>
            {{ $agentFooter['email'] }}
            @if(!empty($agentFooter['phone']))
                <br>{{ $agentFooter['phone'] }}
            @endif
        </p>
    </div>
@endif
