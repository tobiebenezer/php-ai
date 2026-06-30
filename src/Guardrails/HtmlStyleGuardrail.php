<?php

namespace Tobiebenezer\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\InstructionGuardrail;

class HtmlStyleGuardrail implements InstructionGuardrail
{
    protected $styleReference;

    public function __construct()
    {
        $this->styleReference = $this->buildStyleReference();
    }

    public function appliesTo(GuardrailContext $context)
    {
        return true;
    }

    public function instructions(GuardrailContext $context)
    {
        return [
            $this->formatDirective(),
            $this->styleReference,
        ];
    }

    protected function formatDirective()
    {
        return <<<TEXT
You MUST format every response as a complete, styled HTML document using the CSS classes listed below.
Wrap content in a <div class="card"><div class="card-body"> ... </div></div> container for a polished look.
Use <table class="table table-striped table-bordered table-sm"> for tabular data.
Use <span class="badge badge-{color}"> for status indicators.
Use <div class="alert alert-{type}"> for callouts or summaries.

DO NOT return plain text, JSON arrays, or markdown. Return HTML only.
TEXT;
    }

    protected function buildStyleReference()
    {
        return <<<STYLE
--- Available CSS Classes (Material Dashboard 3 + Bootstrap 4) ---

=== Layout & Containers ===
g-sidenav-show          Full admin layout wrapper
bg-gray-400 / bg-gray-200 / bg-gray-100   Gray backgrounds
main-content            Main content area wrapper
position-relative       Relative positioning
border-radius-lg / border-radius-xl   Rounded corners
max-height-vh-100 / h-100    Full height
container / container-fluid    Bootstrap containers
row / col-* / col-lg-* / col-md-* / col-12   Grid system
mx-auto / my-auto       Auto margins (centering)

=== Cards ===
card                    Card container
card-body               Card body content area
card-header             Card header
z-index-0               Stacking order
fadeIn3 / fadeInBottom  Animation classes

=== Colors & Gradients ===
bg-gradient-primary     Primary gradient (blue/purple)
bg-gradient-dark        Dark gradient
bg-gradient-orange      Orange gradient
shadow-primary          Primary-colored shadow
text-dark / text-white  Text colors
text-gray-500 / text-gray-600     Gray text
bg-primary / bg-success / bg-danger / bg-warning / bg-info   Bootstrap solid backgrounds
text-primary / text-success / text-danger / text-warning     Bootstrap text colors

=== Sidebar (Sidenav) ===
sidenav                 Sidebar container
navbar-vertical         Vertical navigation
navbar-expand-xs        Responsive expand
border-0                No border
bg-gradient-dark        Dark gradient sidebar background
fixed-start             Fixed to left
ms-3                    Margin start (left margin)

=== Typography ===
font-sans               Sans-serif font stack
h1 - h6                 Heading levels
font-semibold / font-medium / font-weight-5 / font-weight-6   Font weights
text-xl / text-sm       Font sizes
leading-tight           Line height
text-center / text-right   Text alignment

=== Tables (simple-datatables + Bootstrap) ===
table                   Base table
table-striped           Alternating row colors
table-bordered          Borders around all cells
table-sm                Compact table
table-responsive        Horizontal scroll wrapper
thead / tbody           Table sections

=== Buttons ===
btn                     Base button
btn-primary / btn-secondary / btn-success / btn-danger   Colored buttons
btn-sm / btn-lg         Button sizes
btn-block / btn-full-width   Full width button
btn-round               Rounded button

=== Badges & Alerts ===
badge                   Badge / pill
badge-primary / badge-success / badge-danger / badge-warning / badge-info   Colored badges
alert                   Alert box
alert-success / alert-danger / alert-warning / alert-info   Colored alerts

=== Forms ===
form-group              Form field wrapper
form-control            Input field
form-control-sm         Compact input

=== Navigation ===
navbar                  Navbar container
navbar-static-top       Top navbar
nav / navbar-nav / nav-item / nav-link   Nav components

=== Icons ===
material-icons          Material Icons (use <span class="material-icons">icon_name</span>)
fa / fas / far          Font Awesome icons

=== Utilities ===
d-print-none            Hide when printing
m-0 / p-0               Zero margin/padding
mt-* / mb-* / pt-* / pb-*   Margin/padding top/bottom (0-5 or 0-350 for Fiama)
mx-3 / my-auto          Horizontal margin / vertical auto
float-right / clearfix   Float and clear
shadow / shadow-sm / shadow-lg   Box shadows
box-shadow              Custom box shadow
opacity-6               Opacity utility

=== Page Header ===
page-header             Hero/page header section
min-vh-100              Minimum full viewport height
mask                    Overlay mask
bg-image                Background image
position-relative       Relative positioning

=== Spacing Scale (Material Dashboard) ===
0 through 5 for m-*, p-*, mt-*, mb-*, pt-*, pb-*, mx-*, my-*
Example: mt-n4 (negative margin top 4), mx-3 (horizontal margin 3)

=== Responsive Breakpoints ===
sm: (640px), md: (768px), lg: (1024px), xl: (1280px)
Prefix: sm:flex, lg:px-8, etc. (Tailwind-style responsive prefixes)

=== Example HTML Skeleton ===
<div class="card">
  <div class="card-header"><h5 class="font-semibold">Title</h5></div>
  <div class="card-body">
    <table class="table table-striped table-bordered table-sm">
      <thead><tr><th>Column A</th><th>Column B</th></tr></thead>
      <tbody>
        <tr><td>Value</td><td><span class="badge badge-success">Active</span></td></tr>
      </tbody>
    </table>
  </div>
</div>
STYLE;
    }
}
