<table id="example" class="table table-striped table-bordered" cellspacing="0" width="100%">
    <thead>
    <tr>
        <th>Name</th>
        <th>Description</th>
        <th>Slug</th>
        <th>Parent Category</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    @foreach($categories as $category)
        <tr>
            <td>{{ $category->name }}</td>
            <td>{{ $category->description }}</td>
            <td>{{ $category->slug }}</td>
            <td>{{ $category->parent_id }}</td>
            <td>
                <a class="btn btn-success" href="{{ route('admin.categories.show', $category->id) }}">View</a>
                <a class="btn btn-primary" href="{{ route('admin.categories.edit', $category->id) }}">Edit</a>
                <form action="{{ route('admin.categories.destroy', $category->id) }}" method="POST" style="display: inline;">
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <button type='submit' class="btn btn-danger">Delete</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="pull-right">
    {{ $categories->links() }}
</div>