Testing Component

@php
    $dynamicComponent = 'workbench::test-component';
@endphp

<x-workbench::test-component count="22"/>
<x-workbench::test-inline-component count="44"/>
<x-dynamic-component :component="$dynamicComponent" count="4"/>
<x-workbench::deeper.deeper-component/>
