<label class="field">{{ $label }}
@if($required??false)<span aria-hidden="true"> *</span>
@endif
@if(($type??'text')==='textarea')<textarea name="{{ $name }}" rows="{{ $rows??4 }}"
@required($required??false)>{{ old($name,$value??'') }}</textarea>
@elseif(($type??'text')==='select')<select name="{{ $name }}"
@required($required??false)>
@foreach($options as $k=>$v)<option value="{{ $k }}"
@selected((string)old($name,$value??'')===(string)$k)>{{ $v }}</option>
@endforeach</select>
@else<input name="{{ $name }}" type="{{ $type??'text' }}"
@if(!in_array($type??'text',['file','password'])) value="{{ old($name,$value??'') }}"
@endif
@if(isset($autocomplete)) autocomplete="{{ $autocomplete }}"
@endif
@required($required??false)>
@endif
@if(isset($help))<small class="muted">{{ $help }}</small>
@endif</label>
