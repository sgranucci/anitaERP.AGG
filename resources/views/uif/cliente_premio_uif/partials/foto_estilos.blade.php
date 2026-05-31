<style>
    .premio-foto-thumb-link { display: inline-block; line-height: 0; }
    .premio-foto-thumb {
        max-height: 56px;
        max-width: 56px;
        width: auto;
        height: auto;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #d2d6de;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        vertical-align: middle;
        background: #f8f9fa;
    }
    .premio-foto-thumb-link:hover .premio-foto-thumb {
        border-color: #3c8dbc;
        box-shadow: 0 2px 6px rgba(60, 141, 188, 0.25);
    }
    .premio-foto-sin-imagen {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 6px;
        background: #f4f4f4;
        border: 1px dashed #ccc;
        font-size: 18px;
        color: #aaa;
    }
    .premio-foto-actual {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 12px;
    }
    .premio-foto-preview-link {
        display: inline-block;
        line-height: 0;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #d2d6de;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .premio-foto-preview-link:hover {
        border-color: #3c8dbc;
        box-shadow: 0 4px 12px rgba(60, 141, 188, 0.2);
    }
    .premio-foto-preview-img {
        display: block;
        max-width: 140px;
        max-height: 180px;
        width: auto;
        height: auto;
        object-fit: contain;
        background: #f8f9fa;
    }
    .premio-foto-viewer-body {
        padding: 24px 16px 20px;
        background: linear-gradient(180deg, #f8f9fa 0%, #fff 40%);
    }
    .premio-foto-viewer-figure {
        display: inline-block;
        margin: 0 auto;
        padding: 12px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        max-width: 100%;
    }
    .premio-foto-viewer-img {
        display: block;
        max-width: min(100%, 520px);
        max-height: 70vh;
        width: auto;
        height: auto;
        margin: 0 auto;
        object-fit: contain;
        border-radius: 4px;
    }
</style>
