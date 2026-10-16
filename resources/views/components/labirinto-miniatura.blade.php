@props(['linhas', 'nome'])

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 130 130"
    width="130"
    height="130"
    role="img"
    aria-label="{{ $nome }}"
>
    @foreach ($linhas as $y => $linha)
        @foreach (str_split($linha) as $x => $celula)
            <rect x="{{ $x * 10 }}" y="{{ $y * 10 }}" width="10" height="10"
                fill="{{ $celula === '#' ? '#20201F' : (in_array($celula, ['I', 'C'], true) ? '#FF684D' : '#F7F3EC') }}" />
            @if (in_array($celula, ['I', 'C'], true))
                <text x="{{ $x * 10 + 5 }}" y="{{ $y * 10 + 8 }}" text-anchor="middle"
                    font-size="8" fill="#20201F">{{ $celula }}</text>
            @endif
        @endforeach
    @endforeach
</svg>
