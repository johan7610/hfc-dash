@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-[#00b4d8] focus:ring-[#00b4d8] rounded-md shadow-sm']) }}>
