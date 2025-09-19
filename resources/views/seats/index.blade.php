@extends('layouts.app')

@section('content')
<h1>Exam Seat Allocation</h1>
<p>Rooms:</p>
<ul>
  @foreach($rooms as $room)
    <li>{{ $room->name }} — {{ $room->total_seats }} seats (layout: {{ $room->layout }})</li>
  @endforeach
</ul>

<form method="POST" action="{{ route('seats.allocate') }}">
  @csrf
  <button type="submit" class="btn btn-primary">Allocate Seats</button>
</form>

<a href="{{ route('seats.map') }}" class="btn btn-secondary mt-3">View Seat Map</a>
@endsection
