<ul style="display:flex;flex-direction:column;gap:.5rem">
    @forelse ($documents as $document)
        <li>
            <a href="{{ route('admin.organiser-document', $document) }}" target="_blank" rel="noopener" style="text-decoration:underline">
                {{ $document->type }} · {{ $document->mime }} · {{ number_format($document->size / 1024) }} KB
            </a>
        </li>
    @empty
        <li>No documents.</li>
    @endforelse
</ul>
