<h1>Nesting views</h1>

@foreach($users as $user)
    @include('nested', ['user' => $user])
@endforeach
