<aside class="admin-sidebar"><p class="eyebrow">ADMINISTRACIÓN</p><details class="admin-menu" open><summary>Administración <i class="bi bi-chevron-down" aria-hidden="true"></i></summary><nav aria-label="Administración">
@foreach(['admin'=>['inboxes','Revisar publicaciones'],'admin.users.index'=>['people','Usuarios'],'admin.system'=>['sliders','Configuración'],'admin.settings'=>['palette','Identidad visual'],'admin.history'=>['clock-history','Historial'],'admin.setup'=>['check2-circle','Estado del sistema']] as $route=>$item)
<a href="{{ route($route) }}" @class(['active'=>request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif><i class="bi bi-{{ $item[0] }}" aria-hidden="true"></i>{{ $item[1] }}</a>
@endforeach
<a href="{{ route('admin.projects') }}" @class(['active'=>request()->routeIs('admin.projects*')])><i class="bi bi-kanban" aria-hidden="true"></i>Proyectos</a>
<a href="{{ route('admin.lines') }}" @class(['active'=>request()->routeIs('admin.lines*')])><i class="bi bi-diagram-3" aria-hidden="true"></i>Líneas de investigación</a>
<a href="{{ route('editor.create') }}"><i class="bi bi-plus-circle" aria-hidden="true"></i>Agregar publicación</a><a href="{{ route('account') }}"><i class="bi bi-person" aria-hidden="true"></i>Mi cuenta</a>
</nav></details></aside>
