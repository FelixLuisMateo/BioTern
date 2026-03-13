<?php
$page_title = 'Knowledge Base';
$base_href = '../';
$page_styles = [
    'assets/css/help-knowledgebase-page.css',
];

include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">

<!-- [ page-header ] start -->
            <div class="row g-0 align-items-center border-bottom help-center-content-header">
                <div class="col-lg-6 offset-lg-3 text-center">
                    <h2 class="fw-bolder mb-2 text-dark">Knowledge Base</h2>
                    <p class="text-muted">A premium web applications with integrate knowledge base.</p>
                    <form action="javascript:void(0);" class="my-4 d-none d-sm-block search-form">
                        <div class="input-group select-wd-sm">
                            <select class="form-control" data-select2-selector="icon">
                                <option data-icon="feather-airplay">Getting Started</option>
                                <option data-icon="feather-link-2">Integrations</option>
                                <option data-icon="feather-archive">Directory</option>
                                <option data-icon="feather-help-circle">FAQ'S</option>
                                <option data-icon="feather-at-sign">Administrator</option>
                                <option data-icon="feather-users">End-Users</option>
                                <option data-icon="feather-life-buoy">Support</option>
                            </select>
                            <input type="text" class="form-control w-25" placeholder="Enter your keyword or question here...">
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-search"></i>
                                <span class="ms-2">Search</span>
                            </button>
                        </div>
                    </form>
                    <div class="mt-2 d-none d-sm-block">
                        <span class="fs-12 text-muted">Popular:</span>
                        <a href="javascript:void(0);" class="badge bg-gray-100 shadow-sm text-muted mx-1">Started</a>
                        <a href="javascript:void(0);" class="badge bg-gray-100 shadow-sm text-muted mx-1">Integrations</a>
                        <a href="javascript:void(0);" class="badge bg-gray-100 shadow-sm text-muted mx-1">Directory</a>
                        <a href="javascript:void(0);" class="badge bg-gray-100 shadow-sm text-muted mx-1">Administrator</a>
                        <a href="javascript:void(0);" class="badge bg-gray-100 shadow-sm text-muted mx-1">Support</a>
                    </div>
                </div>
                <!--! ================================================================ !-->
                <!--! END: Content Sub Header [content-sub-header] !-->
                <!--! ================================================================ !-->
            </div>
            <!-- [ page-header ] end -->
            <!-- [ Main Content ] start -->
            <div class="main-content container-lg px-4 help-center-main-contet-area overflow-visible">
                <!--! BEGIN: [help-quick-card] !-->
                <div class="row help-quick-card">
                    <div class="col-lg-4">
                        <div class="card mb-4 mb-lg-0">
                            <div class="card-body p-5">
                                <div class="wd-50 ht-50 d-flex align-items-center justify-content-center mb-5">
                                    <img src="assets/images/icons/line-icon/idea.png" class="img-fluid" alt="">
                                </div>
                                <h2 class="fs-16 fw-bold mb-3">Knowledge Base</h2>
                                <p class="fs-12 fw-medium text-muted text-truncate-3-line">Lorem ipsum dolor sit, amet consectetur adipisicing elit. Sequi, veniam. Modi quas vero odit asperiores alias libero quae in quam dicta autem et repudiandae ex, molestiae doloremque, explicabo reiciendis minus?</p>
                                <a href="javascript:void(0);" class="fs-12">Learn More &rarr;</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4 mb-lg-0">
                            <div class="card-body p-5">
                                <div class="wd-50 ht-50 d-flex align-items-center justify-content-center mb-5">
                                    <img src="assets/images/icons/line-icon/support.png" class="img-fluid" alt="">
                                </div>
                                <h2 class="fs-16 fw-bold mb-3">Contact Agent</h2>
                                <p class="fs-12 fw-medium text-muted text-truncate-3-line">Lorem ipsum dolor sit, amet consectetur adipisicing elit. Sequi, veniam. Modi quas vero odit asperiores alias libero quae in quam dicta autem et repudiandae ex, molestiae doloremque, explicabo reiciendis minus?</p>
                                <a href="javascript:void(0);" class="fs-12">Learn More &rarr;</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4 mb-lg-0">
                            <div class="card-body p-5">
                                <div class="wd-50 ht-50 d-flex align-items-center justify-content-center mb-5">
                                    <img src="assets/images/icons/line-icon/rocket.png" class="img-fluid" alt="">
                                </div>
                                <h2 class="fs-16 fw-bold mb-3">Community Forum</h2>
                                <p class="fs-12 fw-medium text-muted text-truncate-3-line">Lorem ipsum dolor sit, amet consectetur adipisicing elit. Sequi, veniam. Modi quas vero odit asperiores alias libero quae in quam dicta autem et repudiandae ex, molestiae doloremque, explicabo reiciendis minus?</p>
                                <a href="javascript:void(0);" class="fs-12">Learn More &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>
                <!--! BEGIN: [topic-category-section] !-->
                <section class="topic-category-section">
                    <div class="d-flex flex-column align-items-center justify-content-center mb-5">
                        <h2 class="fs-20 fw-bold mb-3">Documentation Category</h2>
                        <p class="px-5 mx-5 text-center text-muted text-truncate-3-line">Lorem ipsum dolor sit amet consectetur adipisicing elit. Minus laboriosam obcaecati fuga repellat animi quam nesciunt maiores dolorem corporis debitis incidunt, accusantium corrupti dignissimos repellendus, saepe accusamus expedita necessitatibus.</p>
                    </div>
                    <div class="row">
                        <div class="col-xl-4 col-lg-6">
                            <div class="card p-4 mb-4">
                                <div class="d-sm-flex align-items-center">
                                    <div class="wd-50 ht-50 p-2 d-flex align-items-center justify-content-center border rounded-3">
                                        <img src="assets/images/icons/line-icon/safe.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="ms-0 ms-sm-3 mt-4 mt-sm-0">
                                        <h2 class="fs-14 fw-bold mb-1">Getting Started</h2>
                                        <span class="fs-10 fw-semibold text-uppercase text-muted">6 topics category</span>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0 mt-4 ms-sm-5 ps-sm-3">
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Getting Started</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Adding End Users</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Applications</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Video Tutorials</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Step by Step Intigrations Guide</a>
                                    </li>
                                </ul>
                                <div class="mt-4 ms-5 ps-3">
                                    <a href="javascript:void(0);" class="fs-12">More Topics &rarr;</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card p-4 mb-4">
                                <div class="d-sm-flex align-items-center">
                                    <div class="wd-50 ht-50 p-2 d-flex align-items-center justify-content-center border rounded-3">
                                        <img src="assets/images/icons/line-icon/mexican.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="ms-0 ms-sm-3 mt-4 mt-sm-0">
                                        <h2 class="fs-14 fw-bold mb-1">User Shortwave</h2>
                                        <span class="fs-10 fw-semibold text-uppercase text-muted">8 topics category</span>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0 mt-4 ms-sm-5 ps-sm-3">
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Getting Started</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Adding End Users</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Applications</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Video Tutorials</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Step by Step Intigrations Guide</a>
                                    </li>
                                </ul>
                                <div class="mt-4 ms-5 ps-3">
                                    <a href="javascript:void(0);" class="fs-12">More Topics &rarr;</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card p-4 mb-4">
                                <div class="d-sm-flex align-items-center">
                                    <div class="wd-50 ht-50 p-2 d-flex align-items-center justify-content-center border rounded-3">
                                        <img src="assets/images/icons/line-icon/shield.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="ms-0 ms-sm-3 mt-4 mt-sm-0">
                                        <h2 class="fs-14 fw-bold mb-1">Settings & Preferance</h2>
                                        <span class="fs-10 fw-semibold text-uppercase text-muted">9 topics category</span>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0 mt-4 ms-sm-5 ps-sm-3">
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Getting Started</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Adding End Users</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Applications</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Video Tutorials</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Step by Step Intigrations Guide</a>
                                    </li>
                                </ul>
                                <div class="mt-4 ms-5 ps-3">
                                    <a href="javascript:void(0);" class="fs-12">More Topics &rarr;</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card p-4 mb-4">
                                <div class="d-sm-flex align-items-center">
                                    <div class="wd-50 ht-50 p-2 d-flex align-items-center justify-content-center border rounded-3">
                                        <img src="assets/images/icons/line-icon/money-bag.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="ms-0 ms-sm-3 mt-4 mt-sm-0">
                                        <h2 class="fs-14 fw-bold mb-1">Terms & Billing</h2>
                                        <span class="fs-10 fw-semibold text-uppercase text-muted">10 topics category</span>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0 mt-4 ms-sm-5 ps-sm-3">
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Getting Started</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Adding End Users</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Applications</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Video Tutorials</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Step by Step Intigrations Guide</a>
                                    </li>
                                </ul>
                                <div class="mt-4 ms-5 ps-3">
                                    <a href="javascript:void(0);" class="fs-12">More Topics &rarr;</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card p-4 mb-4">
                                <div class="d-sm-flex align-items-center">
                                    <div class="wd-50 ht-50 p-2 d-flex align-items-center justify-content-center border rounded-3">
                                        <img src="assets/images/icons/line-icon/lifebuoy.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="ms-0 ms-sm-3 mt-4 mt-sm-0">
                                        <h2 class="fs-14 fw-bold mb-1">Integrations</h2>
                                        <span class="fs-10 fw-semibold text-uppercase text-muted">8 topics category</span>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0 mt-4 ms-sm-5 ps-sm-3">
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Getting Started</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Adding End Users</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Applications</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Video Tutorials</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Step by Step Intigrations Guide</a>
                                    </li>
                                </ul>
                                <div class="mt-4 ms-5 ps-3">
                                    <a href="javascript:void(0);" class="fs-12">More Topics &rarr;</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card p-4 mb-4">
                                <div class="d-sm-flex align-items-center">
                                    <div class="wd-50 ht-50 p-2 d-flex align-items-center justify-content-center border rounded-3">
                                        <img src="assets/images/icons/line-icon/award.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="ms-0 ms-sm-3 mt-4 mt-sm-0">
                                        <h2 class="fs-14 fw-bold mb-1">Troubleshooting</h2>
                                        <span class="fs-10 fw-semibold text-uppercase text-muted">7 topics category</span>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0 mt-4 ms-sm-5 ps-sm-3">
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Getting Started</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Adding End Users</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Applications</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Integration Video Tutorials</a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="feather-file-text me-2 fs-13"></i>
                                        <a href="javascript:void(0);" class="fs-13 fw-medium" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">Step by Step Intigrations Guide</a>
                                    </li>
                                </ul>
                                <div class="mt-4 ms-5 ps-3">
                                    <a href="javascript:void(0);" class="fs-12">More Topics &rarr;</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <!--! BEGIN: [topic-tranding-section] !-->
                <section class="topic-tranding-section">
                    <div class="d-flex flex-column align-items-center justify-content-center mb-5">
                        <h2 class="fs-20 fw-bold mb-3">Tranding Topics</h2>
                        <p class="px-5 mx-5 text-center text-muted text-truncate-3-line">Lorem ipsum dolor sit amet consectetur adipisicing elit. Minus laboriosam obcaecati fuga repellat animi quam nesciunt maiores dolorem corporis debitis incidunt, accusantium corrupti dignissimos repellendus, saepe accusamus expedita necessitatibus.</p>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to upload data to the system?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to draw a land plot on a map?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to to view expire services?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to integrate new web applications?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How do I set the geometry of an object?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to filter object on the map?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to count the number of document in the register?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to upload data to the system?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to draw a land plot on a map?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to to view expire services?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to integrate new web applications?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How do I set the geometry of an object?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to filter object on the map?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card border rounded-3 mb-3 overflow-hidden">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <a href="javascript:void(0);" class="text-truncate-1-line" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">How to count the number of document in the register?</a>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-sm me-3" data-bs-toggle="offcanvas" data-bs-target="#topicsDetailsOffcanvas">
                                        <i class="feather-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <!--! BEGIN: [still-question-section] !-->
                <section class="still-question-section">
                    <div class="d-flex flex-column align-items-center justify-content-center mb-5">
                        <h2 class="fs-20 fw-bold mb-3">Still Have A Question?</h2>
                        <p class="px-5 mx-5 text-center text-muted text-truncate-3-line">Lorem ipsum dolor sit amet consectetur adipisicing elit. Minus laboriosam obcaecati fuga repellat animi quam nesciunt maiores dolorem corporis debitis incidunt, accusantium corrupti dignissimos repellendus, saepe accusamus expedita necessitatibus.</p>
                    </div>
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card card-body pb-0 pb-lg-4 text-center">
                                <a href="tel:+13756589321" class="card stretch stretch-full p-5 mb-4 mb-lg-0 d-flex flex-column flex-fill align-items-center justify-content-center border rounded-3">
                                    <div class="mb-4 wd-50 ht-50">
                                        <img src="assets/images/icons/line-icon/phone.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="fs-14 fw-bold d-block mb-1">+1 (375) 658 9321</div>
                                    <div class="fs-12 fw-medium text-muted text-truncate-1-line">We are always heppy to help.</div>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card card-body pb-0 pb-lg-4 text-center">
                                <a href="mailto:support@helpcenter.com" class="card stretch stretch-full p-5 mb-4 mb-lg-0 d-flex flex-column flex-fill align-items-center justify-content-center border rounded-3">
                                    <div class="mb-4 wd-50 ht-50">
                                        <img src="assets/images/icons/line-icon/email.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="fs-14 fw-bold d-block mb-1">support@helpcenter.com</div>
                                    <div class="fs-12 fw-medium text-muted text-truncate-1-line">The best way to get answer faster.</div>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card card-body pb-0 pb-lg-4 text-center">
                                <a href="settings-support.php" class="card stretch stretch-full p-5 mb-4 mb-lg-0 d-flex flex-column flex-fill align-items-center justify-content-center border rounded-3">
                                    <div class="mb-4 wd-50 ht-50">
                                        <img src="assets/images/icons/line-icon/notebook.png" class="img-fluid" alt="">
                                    </div>
                                    <div class="fs-14 fw-bold d-block mb-1">Submit Ticket</div>
                                    <div class="fs-12 fw-medium text-muted text-truncate-1-line">The best way to get answer faster.</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

<!--! BEGIN: Topics Details Offcanvas !-->
    <!--! ================================================================ !-->
    <div class="offcanvas offcanvas-end topics-details-offcanvas" tabindex="-1" id="topicsDetailsOffcanvas" aria-labelledby="topicsDetailsOffcanvas">
        <div class="offcanvas-header border-bottom px-4">
            <div class="d-flex">
                <a href="javascript:void(0);">Help</a>
                <span class="mx-2 text-muted">/</span>
                <a href="javascript:void(0);">Started</a>
                <span class="mx-2 text-muted">/</span>
                <div class="text-muted">Integration</div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="p-lg-5 mx-lg-5 help-center details-content-body">
                <h2 class="fs-18">How to filter object on the map?</h2>
                <p class="fs-12 text-muted">Last Updates: <span class="text-dark fw-medium">26 May, 2023</span></p>
                <hr class="my-4">
                <h4 class="fs-14">Hey there...!!!</h4>
                <p class="text-muted">Lorem ipsum dolor sit amet consectetur, adipisicing elit. Assumenda consectetur illo at placeat cum nulla repellat expedita nostrum praesentium blanditiis repudiandae commodi tempore debitis odio, doloremque magni eaque soluta cupiditate ullam harum, necessitatibus sunt voluptas eum laboriosam. Consequatur nobis fuga fugiat deleniti veritatis sint, suscipit magni tempora labore. Neque, eaque?</p>
                <p class="text-muted">Lorem ipsum dolor sit amet consectetur, adipisicing elit. Assumenda consectetur illo at placeat cum nulla repellat expedita nostrum praesentium blanditiis repudiandae commodi tempore debitis odio, doloremque magni eaque soluta cupiditate ullam harum, necessitatibus sunt voluptas eum laboriosam. Consequatur nobis fuga fugiat deleniti veritatis sint, suscipit magni tempora labore. Neque, eaque?</p>
                <ul class="text-muted my-4">
                    <li class="mb-2"><strong class="text-dark">Integration</strong> Applications</li>
                    <li class="mb-2"><strong class="text-dark">Integration</strong> Video Tutorials</li>
                    <li class="mb-2"><strong class="text-dark">Step by Step</strong> Intigrations Guide</li>
                    <li class="mb-2"><strong class="text-dark">Add favorite</strong> for quick and messaging</li>
                    <li class="mb-2"><strong class="text-dark">Fine-tune</strong> your notifications to focus on that matters</li>
                    <li><strong class="text-dark">Trigger your inbox</strong> and own your email experience</li>
                </ul>
                <div class="mt-5">
                    <h2 class="fs-13 fw-700 mb-3">Integration Features</h2>
                    <ul class="list-unstyled">
                        <li class="d-flex align-items-center mb-2">
                            <span class="avatar-text avatar-sm bg-soft-success text-success me-2">
                                <i class="feather-check fs-10"></i>
                            </span>
                            <span>Access basic company informations and details.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <span class="avatar-text avatar-sm bg-soft-success text-success me-2">
                                <i class="feather-check fs-10"></i>
                            </span>
                            <span>Access and edite bugs reports and create new issues.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <span class="avatar-text avatar-sm bg-soft-success text-success me-2">
                                <i class="feather-check fs-10"></i>
                            </span>
                            <span>Change issues status and assignee of issues.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <span class="avatar-text avatar-sm bg-soft-success text-success me-2">
                                <i class="feather-check fs-10"></i>
                            </span>
                            <span>Open and resolve intercom conversations.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <span class="avatar-text avatar-sm bg-soft-success text-success me-2">
                                <i class="feather-check fs-10"></i>
                            </span>
                            <span>Add or remover users and change user roles.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <span class="avatar-text avatar-sm bg-soft-success text-success me-2">
                                <i class="feather-check fs-10"></i>
                            </span>
                            <span>We care about your Privacy in our <a href="javascript:void(0);">Privacy &amp; Policy</a>.</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <span class="avatar-text avatar-sm bg-soft-success text-success me-2">
                                <i class="feather-check fs-10"></i>
                            </span>
                            <span>By clicking allow access, your authorize untitled to access your informations.</span>
                        </li>
                    </ul>
                </div>
                <div class="mb-5">
                    <pre><code class="language-html">					
&lt;!DOCTYPE html>
&lt;html lang="zxx">
	&lt;head>
		&lt;meta charset="utf-8">
		&lt;meta http-equiv="x-ua-compatible" content="IE=edge">
		&lt;meta name="viewport" content="width=device-width, initial-scale=1">
		&lt;meta name="description" content="">
		&lt;meta name="keyword" content="">
		&lt;meta name="author" content="theme_ocean">
		&lt;!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
		&lt;!--! BEGIN: Apps Title-->
		&lt;title>Help Center&lt;/title>
		&lt;!--! END:  Apps Title-->
		&lt;!--! BEGIN: Favicon-->
		&lt;link type="image/x-icon" rel="shortcut icon" href=".assets/images/favicon.ico">
		&lt;!--! END: Favicon-->
		&lt;!--! BEGIN: Bootstrap CSS-->
		&lt;link type="text/css" rel="stylesheet" href=".assets/css/bootstrap.min.css">
		&lt;!--! END: Bootstrap CSS-->
		&lt;!--! BEGIN: Vendors CSS-->
		&lt;link type="text/css" rel="stylesheet" href=".assets/vendors/css/vendors.min.css">
		&lt;!--! END: Vendors CSS-->
		&lt;!--! BEGIN: Custom CSS-->
		&lt;link type="text/css" rel="stylesheet" href=".assets/css/theme.min.css">
		&lt;!--! END: Custom CSS-->
		&lt;!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
		&lt;!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
		&lt;!--[if lt IE 9]>
			&lt;script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js">&lt;/script>
			&lt;script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js">&lt;/script>
		&lt;![endif]-->
	&lt;/head>
&lt;/html>
					</code>
						</pre>
                </div>
                <h4 class="fs-13">ðŸ‘‹ Threds</h4>
                <p class="text-muted mb-4">Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque officiis minus omnis veniam cupiditate molestiae itaque hic quibusdam fuga modi quam cumque recusandae harum nulla vero, tempore possimus aliquid animi!</p>
                <div class="mb-5">
                    <pre><code class="language-js">
const Prism = require('prismjs');

// The code snippet you want to highlight, as a string
const code = `var data = 1;`;

// Returns a highlighted HTML string
const html = Prism.highlight(code, Prism.languages.javascript, 'javascript');
						</code></pre>
                </div>
                <h4 class="fs-13">ðŸ“Œ Inbox</h4>
                <p class="text-muted mb-4">Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque officiis minus omnis veniam cupiditate molestiae itaque hic quibusdam fuga modi quam cumque recusandae harum nulla vero, tempore possimus aliquid animi!</p>
                <div class="mb-5">
                    <pre><code class="language-js">
const Prism = require('prismjs');

// The code snippet you want to highlight, as a string
const code = `var data = 1;`;

// Returns a highlighted HTML string
const html = Prism.highlight(code, Prism.languages.javascript, 'javascript');
						</code></pre>
                </div>
                <h4 class="fs-13">ðŸ‘™ Bundles</h4>
                <p class="text-muted mb-4">Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque officiis minus omnis veniam cupiditate molestiae itaque hic quibusdam fuga modi quam cumque recusandae harum nulla vero, tempore possimus aliquid animi!</p>
                <div class="mb-5">
                    <pre><code class="language-js">
const Prism = require('prismjs');

// The code snippet you want to highlight, as a string
const code = `var data = 1;`;

// Returns a highlighted HTML string
const html = Prism.highlight(code, Prism.languages.javascript, 'javascript');
						</code></pre>
                </div>
                <h4 class="fs-13">Snoozen</h4>
                <p class="text-muted mb-4">Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque officiis minus omnis veniam cupiditate molestiae itaque hic quibusdam fuga modi quam cumque recusandae harum nulla vero, tempore possimus aliquid animi!</p>
                <div class="mb-5">
                    <pre><code class="language-js">
const Prism = require('prismjs');

// The code snippet you want to highlight, as a string
const code = `var data = 1;`;

// Returns a highlighted HTML string
const html = Prism.highlight(code, Prism.languages.javascript, 'javascript');
						</code></pre>
                </div>
                <h4 class="fs-13">ðŸŽ‚ Groups</h4>
                <p class="text-muted mb-4">Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque officiis minus omnis veniam cupiditate molestiae itaque hic quibusdam fuga modi quam cumque recusandae harum nulla vero, tempore possimus aliquid animi!</p>
                <div class="mb-5">
                    <pre><code class="language-js">
const Prism = require('prismjs');

// The code snippet you want to highlight, as a string
const code = `var data = 1;`;

// Returns a highlighted HTML string
const html = Prism.highlight(code, Prism.languages.javascript, 'javascript');
						</code></pre>
                </div>
                <h4 class="fs-13">ðŸ˜³ Channel</h4>
                <p class="text-muted mb-4">Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque officiis minus omnis veniam cupiditate molestiae itaque hic quibusdam fuga modi quam cumque recusandae harum nulla vero, tempore possimus aliquid animi!</p>
                <div class="mb-5">
                    <pre><code class="language-js">
const Prism = require('prismjs');

// The code snippet you want to highlight, as a string
const code = `var data = 1;`;

// Returns a highlighted HTML string
const html = Prism.highlight(code, Prism.languages.javascript, 'javascript');
						</code></pre>
                </div>
                <h4 class="fs-13">ðŸŽ‰ Favorite</h4>
                <p class="text-muted mb-4">Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque officiis minus omnis veniam cupiditate molestiae itaque hic quibusdam fuga modi quam cumque recusandae harum nulla vero, tempore possimus aliquid animi!</p>
                <div>
                    <pre><code class="language-js">
const Prism = require('prismjs');

// The code snippet you want to highlight, as a string
const code = `var data = 1;`;

// Returns a highlighted HTML string
const html = Prism.highlight(code, Prism.languages.javascript, 'javascript');
						</code></pre>
                </div>
                <hr class="my-5">
                <p class="text-muted">Lorem ipsum dolor sit amet consectetur, adipisicing elit. Assumenda consectetur illo at placeat cum nulla repellat expedita nostrum praesentium blanditiis repudiandae commodi tempore debitis odio, doloremque magni eaque soluta cupiditate ullam harum, necessitatibus sunt voluptas eum laboriosam. Consequatur nobis fuga fugiat deleniti veritatis sint, suscipit magni tempora labore. Neque, eaque?</p>
                <ul class="text-muted my-4">
                    <li class="mb-2"><strong class="text-dark">Integration</strong> Applications</li>
                    <li class="mb-2"><strong class="text-dark">Integration</strong> Video Tutorials</li>
                    <li class="mb-2"><strong class="text-dark">Step by Step</strong> Intigrations Guide</li>
                    <li class="mb-2"><strong class="text-dark">Add favorite</strong> for quick and messaging</li>
                    <li class="mb-2"><strong class="text-dark">Fine-tune</strong> your notifications to focus on that matters</li>
                    <li><strong class="text-dark">Trigger your inbox</strong> and own your email experience</li>
                </ul>
                <hr class="my-5">
                <div class="w-100 p-5 bg-gray-100 text-center">
                    <h2 class="fs-16 mb-2">Having trouble logging in?</h2>
                    <p class="text-muted">Explore some common issues or solutions or get in tuch with us.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="javascript:void(0);" class="btn btn-sm btn-success">Read FAQ's</a>
                        <a href="javascript:void(0);" class="btn btn-sm btn-danger">Contact Support</a>
                    </div>
                </div>
                <div class="d-flex justify-content-center mt-5">
                    <div class="d-flex align-items-center text-muted">
                        <fs-11 class="me-3">Is this article helpful?</fs-11>
                        <a href="javascript:void(0);" class="wd-15 ht-15 d-flex align-items-center justify-content-center p-2 rounded-3 bg-gray-100 m-1">
                            <i class="feather-x fs-12 text-danger"></i>
                        </a>
                        <a href="javascript:void(0);" class="wd-15 ht-15 d-flex align-items-center justify-content-center p-2 rounded-3 bg-gray-100 m-1">
                            <i class="feather-check fs-12 text-success"></i>
                        </a>
                    </div>
                </div>
                <hr class="my-5">
                <div class="mb-4">
                    <h2 class="fs-18 mb-1">Topics in this collections</h2>
                    <p class="fs-12 text-muted">6 More topics in this collections</p>
                </div>
                <div class="row">
                    <div class="col-xxl-6">
                        <div class="card border rounded-3 mb-3 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <a href="javascript:void(0);">How to upload data to the system?</a>
                                </div>
                                <a href="javascript:void(0);" class="avatar-text avatar-sm me-3">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card border rounded-3 mb-3 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <a href="javascript:void(0);">How to draw a land plot on a map?</a>
                                </div>
                                <a href="javascript:void(0);" class="avatar-text avatar-sm me-3">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card border rounded-3 mb-3 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <a href="javascript:void(0);">How to to view expire services?</a>
                                </div>
                                <a href="javascript:void(0);" class="avatar-text avatar-sm me-3">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-6">
                        <div class="card border rounded-3 mb-3 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <a href="javascript:void(0);">How to upload data to the system?</a>
                                </div>
                                <a href="javascript:void(0);" class="avatar-text avatar-sm me-3">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card border rounded-3 mb-3 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <a href="javascript:void(0);">How to draw a land plot on a map?</a>
                                </div>
                                <a href="javascript:void(0);" class="avatar-text avatar-sm me-3">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card border rounded-3 mb-3 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="wd-50 ht-50 bg-gray-100 me-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <a href="javascript:void(0);">How to to view expire services?</a>
                                </div>
                                <a href="javascript:void(0);" class="avatar-text avatar-sm me-3">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--! ================================================================ !-->
    <!--! END: Topics Details Offcanvas !-->

</div> <!-- .nxl-content -->
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>





