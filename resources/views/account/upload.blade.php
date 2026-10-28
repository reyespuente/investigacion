<form method="post" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="form-panel">
@csrf
@include('partials.field',['name'=>'title','label'=>'Título del documento','required'=>true])<div class="form-grid">
@include('partials.field',['name'=>'kind','label'=>'Tipo','type'=>'select','options'=>['publication'=>'Publicación','certification'=>'Certificación','degree'=>'Documento académico','other'=>'Otro']])<label class="field">Publicación relacionada (opcional)<select name="publication_id"><option value="">Documento de mi perfil</option>
@foreach(auth()->user()->researcher->publications()->wherePivot('state','confirmed')->get() as $paper)<option value="{{ $paper->id }}">{{ Str::limit($paper->title,90) }}</option>
@endforeach</select></label></div>
@include('partials.field',['name'=>'document','label'=>'Archivo PDF, hasta 20 MB','type'=>'file','required'=>true])
@include('partials.field',['name'=>'license','label'=>'Derechos o licencia','type'=>'select','options'=>['private'=>'Documento privado','author_permission'=>'Soy titular o tengo autorización expresa','cc_by'=>'Creative Commons Atribución','cc_by_sa'=>'Creative Commons Atribución-CompartirIgual','open_license'=>'Otra licencia que permite esta distribución']])<label class="checkbox"><input type="checkbox" name="rights" required> Declaro que tengo derecho a almacenar este documento y, si lo hago público, a distribuirlo en este repositorio.</label>
<label class="checkbox"><input type="checkbox" name="is_public" value="1"> Publicar el documento en mi perfil o publicación</label>
<p class="small muted">Comprueba que no contenga identificadores, firmas, calificaciones u otros datos personales que no deban divulgarse.</p>
@include('partials.turnstile',['action'=>'upload'])<button class="button">Guardar documento</button></form>
