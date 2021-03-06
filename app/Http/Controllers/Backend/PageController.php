<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\StoreUpdatePageRequest;
use App\Repositories\Contracts\PageRepository;
use Illuminate\Http\Request;

/**
 * Class PageController
 * @package App\Http\Controllers\Backend
 */
class PageController extends BackendController
{
    /**
     * @var \App\Repositories\Contracts\PageRepository
     */
    protected $pageRepository;

    /**
     * PageController constructor.
     * @param \App\Repositories\Contracts\PageRepository $pageRepository
     */
    public function __construct(PageRepository $pageRepository)
    {
        $this->pageRepository = $pageRepository;

        $this->pageRepository->ignorePublishedStatusMode();
    }

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $showTrash = $this->determineIfWantTrash($request);

        $pages = $this->pageRepository->paginate();

        return view('admin.pages.index', compact('pages', 'showTrash'));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    protected function determineIfWantTrash($request)
    {
        return tap($request->has('trash'), function ($isTrash) {
            if ($isTrash) {
                $this->pageRepository->onlyTrashed();
            }
        });
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.pages.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\StoreUpdatePageRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreUpdatePageRequest $request)
    {
        $page = $this->pageRepository->create($request->all());

        return $this->successCreated($page);
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $slug = $this->pageRepository->getSlug($id);

        return redirect()->route('pages.show', array_merge([$slug], $request->query()));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $page = $this->pageRepository->find($id);

        return view('admin.pages.edit', compact('page'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\StoreUpdatePageRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StoreUpdatePageRequest $request, $id)
    {
        $page = $this->pageRepository->update($request->all(), $id);

        return $this->successCreated($page);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $this->pageRepository->delete($id);

        return $this->successDeleted();
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        $this->pageRepository->restore($id);

        return $this->successNoContent();
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDelete($id)
    {
        $this->pageRepository->forceDelete($id);

        return $this->successDeleted();
    }
}
