<section class="dismiss-publication" aria-label="Eliminar de mi bandeja">
    <details class="review-evidence" @if($open ?? false) open @endif>
        <summary><i class="bi bi-trash" aria-hidden="true"></i> ELIMINAR PERMANENTEMENTE</summary>
        <p>Se quitará «{{ $publication->title }}» de todas las listas de tu bandeja. Esta acción es permanente para tu cuenta.</p>
        <p class="small muted">La publicación y sus documentos seguirán disponibles en el repositorio y en las bandejas de los demás investigadores.</p>
        <form method="post" action="{{ route('editor.dismiss', $publication) }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="version" value="{{ $publication->revision }}">
            <label class="checkbox">
                <input type="checkbox" name="confirmation" value="1" required>
                Confirmo que quiero eliminar esta publicación de mi bandeja.
            </label>
            <div class="button-row">
                <button class="button danger" name="intent" value="next">ELIMINAR PERMANENTEMENTE</button>
                <a href="{{ route('account', ['status' => 'pending']) }}">Volver a mi bandeja</a>
            </div>
        </form>
    </details>
</section>
