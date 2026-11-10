@include('partials.field', [
    'name' => 'contact_email',
    'label' => 'Correo público principal',
    'type' => 'email',
    'value' => $researcher->contacts['contact_email'] ?? '',
])
@include('partials.field', [
    'name' => 'email_aliases',
    'label' => 'Alias de correo públicos',
    'type' => 'textarea',
    'value' => implode("\n", $researcher->contacts['email_aliases'] ?? []),
    'help' => 'Agrega hasta 20 correos alternativos usados en publicaciones, uno por línea. Aparecerán en el perfil público.',
])
