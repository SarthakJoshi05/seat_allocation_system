@extends('layouts.app')

@section('content')
<h1>Room-wise Seat Map</h1>

@foreach($roomMaps as $rm)
  <h3>{{ $rm['room']->name }} ({{ $rm['room']->layout }})</h3>
  <table class="table table-bordered">
    <tbody>
    @foreach($rm['map'] as $r => $row)
      <tr>
        @foreach($row as $c => $cell)
          <td style="min-width:120px; vertical-align:middle;">
            @if($cell)
              <strong>{{ $cell->roll_number }}</strong><br>
              {{ $cell->name }}<br>
              {{ $cell->department }} / {{ $cell->subject_code }}<br>
              {{ $cell->gender }} {{ $cell->special_needs ? '(special)' : '' }}
            @else
              <em>Empty</em>
            @endif
          </td>
        @endforeach
      </tr>
    @endforeach
    </tbody>
  </table>
@endforeach

<a href="{{ route('seats.home') }}" class="btn btn-link">Back</a>
@endsection
