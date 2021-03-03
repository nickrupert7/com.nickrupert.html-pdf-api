<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlToPdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConvertController extends Controller
{
    public function convert(Request $request)
    {
        $format = '/^(\d+\.)?\d+((cm)|(mm)|(Q)|(in)|(pc)|(pt)|(px))$/i';

        $this->validate($request, [
            'html' => 'required',
            'size' => [
                'sometimes',
                'required',
                Rule::in(['A5', 'A4', 'A3', 'B5', 'B4', 'JIS-B5', 'JIS-B4', 'letter', 'legal', 'ledger'])
            ],
            'orientation' => [
                'sometimes',
                'required',
                Rule::in('portrait', 'landscape')
            ],
            'width' => ['required_with:height', "regex:$format"],
            'height' => ['required_with:width', "regex:$format"]
        ], [
            'size.in' => 'The size field must be an acceptable value for CSS page-size. See https://developer.mozilla.org/en-US/docs/Web/CSS/@page/size#values',
            'orientation.in' => 'The orientation field must be "portrait" or "landscape"',
            'width.regex' => 'The width field must be an acceptable value for CSS absolute length. See https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Values_and_Units#dimensions',
            'height.regex' => 'The height field must be an acceptable value for CSS absolute length. See https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Values_and_Units#dimensions'
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

        return response(
            $builder->convert(),
            200, [
            'Content-Type' => 'application/pdf'
        ]);
    }
}
