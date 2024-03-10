@props(['disabled' => false, 'options' => []])

<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) !!}>
    <option value="" selected>select option</option>
    @foreach($options as $key => $value)
        <option value="{{ $value }}">{{ $key }}</option>
    @endforeach
</select>
