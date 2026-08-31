<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index()
    {
        return view('admin.blog.index', ['items' => BlogPost::orderBy('order')->get()]);
    }

    public function create()
    {
        return view('admin.blog.form', ['item' => new BlogPost()]);
    }

    public function store(Request $request)
    {
        BlogPost::create($this->validated($request));
        return redirect()->route('admin.blog.index')->with('status', 'Post created.');
    }

    public function edit(BlogPost $blog)
    {
        return view('admin.blog.form', ['item' => $blog]);
    }

    public function update(Request $request, BlogPost $blog)
    {
        $blog->update($this->validated($request));
        return redirect()->route('admin.blog.index')->with('status', 'Post updated.');
    }

    public function destroy(BlogPost $blog)
    {
        $blog->delete();
        return back()->with('status', 'Post deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'image'   => ['nullable', 'string', 'max:255'],
            'title'   => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'order'   => ['nullable', 'integer'],
        ]);
    }
}
