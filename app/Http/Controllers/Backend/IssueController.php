<?php

namespace App\Http\Controllers\Backend;

use App\Category;
use App\Issue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index(Builder $builder)
    {
        $query = Issue::query()->orderBy('created_at', 'desc');
        if(request()->ajax()) {
            return DataTables::eloquent($query)
                ->addColumn('edit', function (Issue $issue) {
                    return view('architect.datatables.form-edit', ['model' => $issue, 'route' => 'issue']);
                })
                ->addColumn('delete', function (Issue $issue) {
                    return view('architect.datatables.form-delete', ['model' => $issue, 'route' => 'issue']);
                })
                ->editColumn('active', function (Issue $issue) {
                    $outletText = $issue->active == true ? "Active" : "Inactive";
                    $badge = $issue->active  == true ? "success" : "danger";
                    return "<a href='javascript:void(0);' class='mb-2 mr-2 badge badge-$badge ? success : danger'>$outletText</a>";
                })
                ->addColumn('category', function (Issue $issue) {
                    return $issue->category->name;
                })
                ->editColumn('created_at', function (Issue $issue) {
                    return Carbon::parse($issue->created_at)->diffForHumans();
                })
                ->rawColumns(['edit', 'active', 'delete'])
                ->toJson();
        }

        $html = $builder->columns([
            ['data' => 'id', 'title' => 'ID'],
            ['data' => 'name', 'title' => 'Name'],
            ['data' => 'active', 'title' => 'Status'],
            ['data' => 'category', 'title' => 'Category'],
            ['data' => 'created_at', 'title' => 'Created At'],
            ['data' => 'edit', 'title' => ''],
            ['data' => 'delete', 'title' => '']
        ]);
        return view('architect.issue.index', compact('html'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('architect.issue.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required', 'string', 'unique:issues,name',
            'category_id' => ['required', 'exists:categories,id'],
            'active' => 'in:on'
        ]);

        $category = Category::findOrFail($request->category_id);

        $outlet = $category->issues()->create([
            'name' => $request->name,
            'active' => $request->has('active') && $request->active == "on" ? true : false,
            'desc' => $request->desc ?: null
        ]);
        return redirect()->route('issue.index')->with('status', 'Issue has been created.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Issue  $issue
     * @return \Illuminate\Http\Response
     */
    public function show(Issue $issue)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Issue  $issue
     * @return \Illuminate\Http\Response
     */
    public function edit(Issue $issue)
    {
        return view('architect.issue.edit', compact('issue'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Issue  $issue
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Issue $issue)
    {
        $request->validate([
            'name' => 'required', 'string', 'unique:issues,name',
            'active' => 'in:on'
        ]);

        $issue->update([
            'name' => $request->name,
            'active' => $request->has('active') && $request->active == "on" ? true : false,
            'desc' => $request->desc ?: null
        ]);
        return redirect()->route('issue.index')->with('status', 'Issue has been updated.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Issue  $issue
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function destroy(Issue $issue)
    {
        if($issue->complains()->count() > 0)
            return redirect()->route('issue.index')->with('failure', "This issue has multple complains assigned to it. First unassign them before procedding to deletion.");
        elseif($issue->category()->count() > 0) {
            return redirect()->route('issue.index')->with('failure', "This issue has multple categories assigned to it. First unassign them before procedding to deletion.");
        }
        elseif ($issue->ratings()->count() > 0) {
            return redirect()->route('issue.index')->with('failure', "This issue has multple rating complains assigned to it. First unassign them before procedding to deletion.");
        }
        try {
            $issue->delete();
        } catch (\Exception $e) {
            return redirect()->route('issue.index')->with('failure', "Failed: " . $e->getMessage());
        }

        return redirect()->route('issue.index')->with('status', 'Issue has been deleted');
    }
}
