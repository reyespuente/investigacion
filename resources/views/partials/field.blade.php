@php $inputId='field-'.$name.'-'.Str::random(8); @endphp
<label class="field" for="{{ $inputId }}"><span class="field-label">{{ $label }} @if($required??false)<span aria-hidden="true">*</span>@endif</span>
@if(($type??'text')==='textarea')
<textarea id="{{ $inputId }}" name="{{ $name }}" rows="{{ $rows??4 }}" @required($required??false)>{{ old($name,$value??'') }}</textarea>
@elseif(($type??'text')==='select')
<select id="{{ $inputId }}" name="{{ $name }}" @required($required??false)>@foreach($options as $k=>$v)<option value="{{ $k }}" @selected((string)old($name,$value??'')===(string)$k)>{{ $v }}</option>@endforeach</select>
@else
<input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type??'text' }}" @if(!in_array($type??'text',['file','password'])) value="{{ old($name,$value??'') }}" @endif @if(isset($autocomplete)) autocomplete="{{ $autocomplete }}" @endif @required($required??false)>
@endif
@if(isset($help))<small class="muted">{{ $help }}</small>@endif
</label>
