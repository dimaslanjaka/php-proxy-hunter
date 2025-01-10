function webgl_start() {
  const debugEl = document.getElementById('debug');

  // Create canvas elements for different contexts
  const canvas = document.createElement('canvas');
  const canvas2 = document.createElement('canvas');
  document.body.appendChild(canvas); // Add canvas1 to the body
  document.body.appendChild(canvas2); // Add canvas2 to the body

  const gl = canvas.getContext('webgl');
  const gld1 = gl.getExtension('WEBGL_debug_renderer_info');
  const gl2 = canvas2.getContext('webgl2');
  const gld2 = gl2.getExtension('WEBGL_debug_renderer_info');

  function getWebGL1Properties(gl) {
    const unmaskedVendor = gl.getParameter(gld1.UNMASKED_VENDOR_WEBGL);
    const unmaskedRenderer = gl.getParameter(gld1.UNMASKED_RENDERER_WEBGL);
    const result = {
      unmaskedVendor,
      unmaskedRenderer,
      vendor: gl.getParameter(gl.VENDOR),
      renderer: gl.getParameter(gl.RENDERER),
      version: gl.getParameter(gl.VERSION),
      shadingLanguage: gl.getParameter(gl.SHADING_LANGUAGE_VERSION),
      maxAnisotropy: gl.getExtension('EXT_texture_filter_anisotropic')
        ? gl.getParameter(gl.getExtension('EXT_texture_filter_anisotropic').MAX_TEXTURE_MAX_ANISOTROPY_EXT)
        : 'N/A',
      aliasedLineWidthRange: gl.getParameter(gl.ALIASED_LINE_WIDTH_RANGE),
      aliasedPointSizeRange: gl.getParameter(gl.ALIASED_POINT_SIZE_RANGE),
      alphaBits: gl.getParameter(gl.ALPHA_BITS),
      blueBits: gl.getParameter(gl.BLUE_BITS),
      depthBits: gl.getParameter(gl.DEPTH_BITS),
      greenBits: gl.getParameter(gl.GREEN_BITS),
      maxCombinedTextureImageUnits: gl.getParameter(gl.MAX_COMBINED_TEXTURE_IMAGE_UNITS),
      maxCubeMapTextureSize: gl.getParameter(gl.MAX_CUBE_MAP_TEXTURE_SIZE),
      maxFragmentUniformVectors: gl.getParameter(gl.MAX_FRAGMENT_UNIFORM_VECTORS),
      maxRenderBufferSize: gl.getParameter(gl.MAX_RENDERBUFFER_SIZE),
      maxTextureImageUnits: gl.getParameter(gl.MAX_TEXTURE_IMAGE_UNITS),
      maxTextureSize: gl.getParameter(gl.MAX_TEXTURE_SIZE),
      maxVaryingVectors: gl.getParameter(gl.MAX_VARYING_VECTORS),
      maxVertexAttribs: gl.getParameter(gl.MAX_VERTEX_ATTRIBS),
      maxVertexTextureImageUnits: gl.getParameter(gl.MAX_VERTEX_TEXTURE_IMAGE_UNITS),
      maxVertexUniformVectors: gl.getParameter(gl.MAX_VERTEX_UNIFORM_VECTORS),
      maxViewportDims: gl.getParameter(gl.MAX_VIEWPORT_DIMS),
      redBits: gl.getParameter(gl.RED_BITS),
      stencilBits: gl.getParameter(gl.STENCIL_BITS),
      subpixelBits: gl.getParameter(gl.SUBPIXEL_BITS),
      sampleBuffers: gl.getParameter(gl.SAMPLE_BUFFERS),
      samples: gl.getParameter(gl.SAMPLES),
      stencilBackValueMask: gl.getParameter(gl.STENCIL_BACK_VALUE_MASK),
      stencilBackWritemask: gl.getParameter(gl.STENCIL_BACK_WRITEMASK),
      stencilValueMask: gl.getParameter(gl.STENCIL_VALUE_MASK),
      stencilWritemask: gl.getParameter(gl.STENCIL_WRITEMASK),
      maxColorAttachmentsWebgl: gl.getParameter(gl.MAX_COLOR_ATTACHMENTS),
      maxDrawBuffersWebgl: gl.getParameter(gl.MAX_DRAW_BUFFERS),
      extensions: gl.getSupportedExtensions().join(',')
    };
    const attributes = gl.getContextAttributes();
    result.attributes_webgl = attributes;
    return result;
  }

  function getWebGL2Properties(gl2) {
    const unmaskedVendor2 = gl2.getParameter(gld2.UNMASKED_VENDOR_WEBGL);
    const unmaskedRenderer2 = gl2.getParameter(gld2.UNMASKED_RENDERER_WEBGL);
    const result = {
      unmaskedRenderer2,
      unmaskedVendor2,
      shadingLanguage2: gl2.getParameter(gl2.SHADING_LANGUAGE_VERSION),
      version2: gl2.getParameter(gl2.VERSION),
      max3DTextureSize2: gl2.getParameter(gl2.MAX_3D_TEXTURE_SIZE),
      maxArrayTextureLayers2: gl2.getParameter(gl2.MAX_ARRAY_TEXTURE_LAYERS),
      maxClientWaitTimeoutWebgl2: gl2.getParameter(gl2.MAX_CLIENT_WAIT_TIMEOUT_WEBGL),
      maxElementIndex2: gl2.getParameter(gl2.MAX_ELEMENT_INDEX),
      maxServerWaitTimeout2: gl2.getParameter(gl2.MAX_SERVER_WAIT_TIMEOUT),
      maxTextureLodBias2: gl2.getParameter(gl2.MAX_TEXTURE_LOD_BIAS),
      maxUniformBufferBindings2: gl2.getParameter(gl2.MAX_UNIFORM_BUFFER_BINDINGS),
      maxUniformBlockSize2: gl2.getParameter(gl2.MAX_UNIFORM_BLOCK_SIZE),
      uniformBufferOffsetAlignment2: gl2.getParameter(gl2.UNIFORM_BUFFER_OFFSET_ALIGNMENT),
      maxCombinedUniformBlocks2: gl2.getParameter(gl2.MAX_COMBINED_UNIFORM_BLOCKS),
      maxCombinedVertexUniformComponents2: gl2.getParameter(gl2.MAX_COMBINED_VERTEX_UNIFORM_COMPONENTS),
      maxCombinedFragmentUniformComponents2: gl2.getParameter(gl2.MAX_COMBINED_FRAGMENT_UNIFORM_COMPONENTS),
      maxElementsVertices2: gl2.getParameter(gl2.MAX_ELEMENTS_VERTICES),
      maxElementsIndices2: gl2.getParameter(gl2.MAX_ELEMENTS_INDICES),
      aliasedLineWidthRange2: gl2.getParameter(gl2.ALIASED_LINE_WIDTH_RANGE),
      aliasedPointSizeRange2: gl2.getParameter(gl2.ALIASED_POINT_SIZE_RANGE),
      extensions2: gl2.getSupportedExtensions().join(',')
    };

    const attributes = gl2.getContextAttributes();
    result.attributes_webgl2 = attributes;

    return result;
  }

  function getWebGLData() {
    const webgl1Data = gl ? getWebGL1Properties(gl) : {};
    const webgl2Data = gl2 ? getWebGL2Properties(gl2) : {};

    const obj = {
      ...webgl1Data,
      ...webgl2Data
    };
    const sortedKeys = Object.keys(obj).sort();

    const sortedObj = sortedKeys.reduce((acc, key) => {
      acc[key] = obj[key];
      return acc;
    }, {});

    return {
      webgl_properties: sortedObj
    };
  }

  // Example usage
  const computed_webgl_data = getWebGLData();
  document.getElementById('webgl-result').innerHTML = JSON.stringify(computed_webgl_data, null, 2);

  const original_data = {
    webgl_properties: {
      unmaskedVendor: 'Google Inc. (Intel)',
      unmaskedRenderer: 'ANGLE (Intel, Intel(R) UHD Graphics (0x00009BA4) Direct3D11 vs_5_0 ps_5_0, D3D11)',
      vendor: 'WebKit',
      renderer: 'WebKit WebGL',
      shadingLanguage: 'WebGL GLSL ES 1.0 (OpenGL ES GLSL ES 1.0 Chromium)',
      version: 'WebGL 1.0 (OpenGL ES 2.0 Chromium)',
      maxAnisotropy: '16',
      shadingLanguage2: 'WebGL GLSL ES 3.00 (OpenGL ES GLSL ES 3.0 Chromium)',
      version2: 'WebGL 2.0 (OpenGL ES 3.0 Chromium)',
      aliasedLineWidthRange: {
        0: 1,
        1: 1
      },
      aliasedPointSizeRange: {
        0: 1,
        1: 1024
      },
      alphaBits: '8',
      blueBits: '8',
      depthBits: '24',
      greenBits: '8',
      maxCombinedTextureImageUnits: '32',
      maxCubeMapTextureSize: '16384',
      maxFragmentUniformVectors: '1024',
      maxRenderBufferSize: '16384',
      maxTextureImageUnits: '16',
      maxTextureSize: '16384',
      maxVaryingVectors: '30',
      maxVertexAttribs: '16',
      maxVertexTextureImageUnits: '16',
      maxVertexUniformVectors: '4096',
      maxViewportDims: {
        0: 32767,
        1: 32767
      },
      redBits: '8',
      stencilBits: '8',
      subpixelBits: '4',
      sampleBuffers: '1',
      samples: '4',
      stencilBackValueMask: '2147483647',
      stencilBackWritemask: '2147483647',
      stencilValueMask: '2147483647',
      stencilWritemask: '2147483647',
      maxColorAttachmentsWebgl: '8',
      maxDrawBuffersWebgl: '8',
      webglContextAttributesDefaults: {
        alpha: true,
        antialias: true,
        depth: true,
        desynchronized: false,
        failIfMajorPerformanceCaveat: false,
        powerPreference: 'default',
        premultipliedAlpha: true,
        preserveDrawingBuffer: false,
        stencil: false,
        xrCompatible: false
      },
      extensions:
        'ANGLE_instanced_arrays,EXT_blend_minmax,EXT_clip_control,EXT_color_buffer_half_float,EXT_depth_clamp,EXT_disjoint_timer_query,EXT_float_blend,EXT_frag_depth,EXT_polygon_offset_clamp,EXT_shader_texture_lod,EXT_texture_compression_bptc,EXT_texture_compression_rgtc,EXT_texture_filter_anisotropic,EXT_texture_mirror_clamp_to_edge,EXT_sRGB,KHR_parallel_shader_compile,OES_element_index_uint,OES_fbo_render_mipmap,OES_standard_derivatives,OES_texture_float,OES_texture_float_linear,OES_texture_half_float,OES_texture_half_float_linear,OES_vertex_array_object,WEBGL_blend_func_extended,WEBGL_color_buffer_float,WEBGL_compressed_texture_s3tc,WEBGL_compressed_texture_s3tc_srgb,WEBGL_debug_renderer_info,WEBGL_debug_shaders,WEBGL_depth_texture,WEBGL_draw_buffers,WEBGL_lose_context,WEBGL_multi_draw,WEBGL_polygon_mode',
      precisionVertexShaderHighFloat: 23,
      rangeMinVertexShaderHighFloat: 127,
      rangeMaxVertexShaderHighFloat: 127,
      precisionVertexShaderMediumFloat: 23,
      rangeMinVertexShaderMediumFloat: 127,
      rangeMaxVertexShaderMediumFloat: 127,
      precisionVertexShaderLowFloat: 23,
      rangeMinVertexShaderLowFloat: 127,
      rangeMaxVertexShaderLowFloat: 127,
      precisionFragmentShaderHighFloat: 23,
      rangeMinFragmentShaderHighFloat: 127,
      rangeMaxFragmentShaderHighFloat: 127,
      precisionFragmentShaderMediumFloat: 23,
      rangeMinFragmentShaderMediumFloat: 127,
      rangeMaxFragmentShaderMediumFloat: 127,
      precisionFragmentShaderLowFloat: 23,
      rangeMinFragmentShaderLowFloat: 127,
      rangeMaxFragmentShaderLowFloat: 127,
      precisionVertexShaderHighInt: 0,
      rangeMinVertexShaderHighInt: 31,
      rangeMaxVertexShaderHighInt: 30,
      precisionVertexShaderMediumInt: 0,
      rangeMinVertexShaderMediumInt: 31,
      rangeMaxVertexShaderMediumInt: 30,
      precisionVertexShaderLowInt: 0,
      rangeMinVertexShaderLowInt: 31,
      rangeMaxVertexShaderLowInt: 30,
      precisionFragmentShaderHighInt: 0,
      rangeMinFragmentShaderHighInt: 31,
      rangeMaxFragmentShaderHighInt: 30,
      precisionFragmentShaderMediumInt: 0,
      rangeMinFragmentShaderMediumInt: 31,
      rangeMaxFragmentShaderMediumInt: 30,
      precisionFragmentShaderLowInt: 0,
      rangeMinFragmentShaderLowInt: 31,
      rangeMaxFragmentShaderLowInt: 30,
      maxVertexUniformComponents2: '16384',
      maxVertexUniformBlocks2: '12',
      maxVertexOutputComponents2: '120',
      maxVaryingComponents2: '120',
      maxTransformFeedbackInterleavedComponents2: '120',
      maxTransformFeedbackSeparateAttribs2: '4',
      maxTransformFeedbackSeparateComponents2: '4',
      maxFragmentUniformComponents2: '4096',
      maxFragmentUniformBlocks2: '12',
      maxFragmentInputComponents2: '120',
      minProgramTexelOffset2: '-8',
      maxProgramTexelOffset2: '7',
      maxDrawBuffers2: '8',
      maxColorAttachments2: '8',
      maxSamples2: '16',
      max3DTextureSize2: '2048',
      maxArrayTextureLayers2: '2048',
      maxClientWaitTimeoutWebgl2: '0',
      maxElementIndex2: '4294967294',
      maxServerWaitTimeout2: '0',
      stencilBackValueMask2: '2147483647',
      stencilBackWritemask2: '2147483647',
      stencilValueMask2: '2147483647',
      stencilWritemask2: '2147483647',
      maxTextureLodBias2: '2',
      maxUniformBufferBindings2: '24',
      maxUniformBlockSize2: '65536',
      uniformBufferOffsetAlignment2: '256',
      maxCombinedUniformBlocks2: '24',
      maxCombinedVertexUniformComponents2: '212992',
      maxCombinedFragmentUniformComponents2: '200704',
      maxElementsVertices2: '2147483647',
      maxElementsIndices2: '2147483647',
      aliasedLineWidthRange2: {
        0: 1,
        1: 1
      },
      aliasedPointSizeRange2: {
        0: 1,
        1: 1024
      },
      webglContextAttributesDefaults2: {
        alpha: true,
        antialias: true,
        depth: true,
        desynchronized: false,
        failIfMajorPerformanceCaveat: false,
        powerPreference: 'default',
        premultipliedAlpha: true,
        preserveDrawingBuffer: false,
        stencil: false,
        xrCompatible: false
      },
      alphaBits2: '8',
      blueBits2: '8',
      depthBits2: '24',
      greenBits2: '8',
      maxCombinedTextureImageUnits2: '32',
      maxCubeMapTextureSize2: '16384',
      maxFragmentUniformVectors2: '1024',
      maxRenderBufferSize2: '16384',
      maxTextureImageUnits2: '16',
      maxTextureSize2: '16384',
      maxVaryingVectors2: '30',
      maxVertexAttribs2: '16',
      maxVertexTextureImageUnits2: '16',
      maxVertexUniformVectors2: '4096',
      maxViewportDims2: {
        0: 32767,
        1: 32767
      },
      redBits2: '8',
      stencilBits2: '8',
      subpixelBits2: '4',
      sampleBuffers2: '1',
      samples2: '4',
      extensions2:
        'EXT_clip_control,EXT_color_buffer_float,EXT_color_buffer_half_float,EXT_conservative_depth,EXT_depth_clamp,EXT_disjoint_timer_query_webgl2,EXT_float_blend,EXT_polygon_offset_clamp,EXT_texture_compression_bptc,EXT_texture_compression_rgtc,EXT_texture_filter_anisotropic,EXT_texture_mirror_clamp_to_edge,EXT_texture_norm16,KHR_parallel_shader_compile,NV_shader_noperspective_interpolation,OES_draw_buffers_indexed,OES_texture_float_linear,OVR_multiview2,WEBGL_blend_func_extended,WEBGL_clip_cull_distance,WEBGL_compressed_texture_s3tc,WEBGL_compressed_texture_s3tc_srgb,WEBGL_debug_renderer_info,WEBGL_debug_shaders,WEBGL_lose_context,WEBGL_multi_draw,WEBGL_polygon_mode,WEBGL_provoking_vertex,WEBGL_stencil_texturing',
      precisionVertexShaderHighFloat2: 23,
      rangeMinVertexShaderHighFloat2: 127,
      rangeMaxVertexShaderHighFloat2: 127,
      precisionVertexShaderMediumFloat2: 23,
      rangeMinVertexShaderMediumFloat2: 127,
      rangeMaxVertexShaderMediumFloat2: 127,
      precisionVertexShaderLowFloat2: 23,
      rangeMinVertexShaderLowFloat2: 127,
      rangeMaxVertexShaderLowFloat2: 127,
      precisionFragmentShaderHighFloat2: 23,
      rangeMinFragmentShaderHighFloat2: 127,
      rangeMaxFragmentShaderHighFloat2: 127,
      precisionFragmentShaderMediumFloat2: 23,
      rangeMinFragmentShaderMediumFloat2: 127,
      rangeMaxFragmentShaderMediumFloat2: 127,
      precisionFragmentShaderLowFloat2: 23,
      rangeMinFragmentShaderLowFloat2: 127,
      rangeMaxFragmentShaderLowFloat2: 127,
      precisionVertexShaderHighInt2: 0,
      rangeMinVertexShaderHighInt2: 31,
      rangeMaxVertexShaderHighInt2: 30,
      precisionVertexShaderMediumInt2: 0,
      rangeMinVertexShaderMediumInt2: 31,
      rangeMaxVertexShaderMediumInt2: 30,
      precisionVertexShaderLowInt2: 0,
      rangeMinVertexShaderLowInt2: 31,
      rangeMaxVertexShaderLowInt2: 30,
      precisionFragmentShaderHighInt2: 0,
      rangeMinFragmentShaderHighInt2: 31,
      rangeMaxFragmentShaderHighInt2: 30,
      precisionFragmentShaderMediumInt2: 0,
      rangeMinFragmentShaderMediumInt2: 31,
      rangeMaxFragmentShaderMediumInt2: 30,
      precisionFragmentShaderLowInt2: 0,
      rangeMinFragmentShaderLowInt2: 31,
      rangeMaxFragmentShaderLowInt2: 30
    }
  };

  const original_data_keys = Object.keys(original_data.webgl_properties);
  const computed_data_keys = Object.keys(computed_webgl_data.webgl_properties);
  const result = original_data_keys.filter((item) => !computed_data_keys.includes(item));
  document.getElementById('missing-keys').innerHTML = JSON.stringify(result, null, 2);

  function getNavigatorProperties() {
    const props = [];

    // Extended list of known properties
    const knownProperties = [
      'appName',
      'appVersion',
      'userAgent',
      'platform',
      'language',
      'languages',
      'cookieEnabled',
      'geolocation',
      'hardwareConcurrency',
      'maxTouchPoints',
      'onLine',
      'product',
      'productSub',
      'vendor',
      'vendorSub',
      'pdfViewerEnabled',
      'deviceMemory'
    ];

    knownProperties.forEach((key) => {
      try {
        // Handle properties that may not be stringifiable
        const value = navigator[key];
        props.push(`navigator.${key}: ${typeof value === 'object' ? JSON.stringify(value, null, 2) : value}`);
      } catch (_e) {
        props.push(`navigator.${key}: [Unable to access]`);
      }
    });

    // Adding screen and window properties manually
    const screenProps = [
      'availHeight',
      'availWidth',
      'width',
      'height',
      'colorDepth',
      'pixelDepth',
      'availLeft',
      'availTop'
    ];

    screenProps.forEach((key) => {
      try {
        const value = screen[key];
        props.push(`screen.${key}: ${value}`);
      } catch (_e) {
        props.push(`screen.${key}: [Unable to access]`);
      }
    });

    const windowProps = ['outerHeight', 'outerWidth', 'devicePixelRatio'];

    windowProps.forEach((key) => {
      try {
        const value = window[key];
        props.push(`window.${key}: ${value}`);
      } catch (_e) {
        props.push(`window.${key}: [Unable to access]`);
      }
    });

    return props.join('\n');
  }

  const preElement = document.getElementById('navigator-properties');
  preElement.textContent = getNavigatorProperties();
}

document.addEventListener('DOMContentLoaded', webgl_start);
