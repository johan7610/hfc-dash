@extends('layouts.corex')

@section('corex-content')
<div class="max-w-xl mx-auto mt-12">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex items-center gap-3 mb-6">
            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-red-600 text-white tracking-wide">TEST</span>
            <h1 class="text-xl font-bold text-gray-900">Document Parser Test</h1>
        </div>
        <p class="text-sm text-gray-500 mb-6">Upload a .docx file to parse it into CoreX Document Structure (CDS) JSON and preview the rendered output.</p>

        <form action="{{ route('docuperfect.parser-test.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-6">
                <label for="document" class="block text-sm font-medium text-gray-700 mb-2">Select .docx file</label>
                <input type="file" name="document" id="document" accept=".docx" required
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                @error('document')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                Parse Document
            </button>
        </form>
    </div>
</div>
@endsection
