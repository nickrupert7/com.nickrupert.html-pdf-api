<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlToPdf;
use Illuminate\Http\Request;

class ConvertController extends Controller
{
    public function convert(Request $request)
    {
        $this->validate($request, [
            'html' => 'required',
            'width' => 'required_with:height',
            'height' => 'required_with:width'
        ]);

        $builder = new HtmlToPdf($request->get('html'));

        if ($request->has('width') && $request->has('height')) {
            $builder->dimensions($request->get('width'), $request->get('height'));
        } else {
            if ($request->has('size')) {
                $builder->size($request->get('size'));
            }

            if ($request->has('orientation')) {
                $builder->orientation($request->get('orientation'));
            }
        }

        return response($builder->convert(), 200, [
            'Content-Type' => 'application/pdf'
        ]);
    }
}
