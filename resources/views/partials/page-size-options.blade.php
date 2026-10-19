@foreach (App\Support\ListPagination::SIZES as $size)
    <option value="{{ $size }}" @selected((int) $selectedSize === $size)>{{ $size }}</option>
@endforeach
